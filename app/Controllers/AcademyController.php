<?php
namespace App\Controllers;

use App\Core\{AdminPath,Auth,ConsoleView,Csrf,Database,FrontendEngine,RateLimiter,RequestGuard};

final class AcademyController
{
    public function admin(): void
    {
        Auth::requireAdmin();
        $active = FrontendEngine::isModuleActive('academy');
        $base = AdminPath::url('/admin/academy');
        $notice = $_SESSION['academy_admin_notice']??'';
        unset($_SESSION['academy_admin_notice']);
        $db = Database::getInstance();
        if ($active && $_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            try {
                $action = ConsoleView::field($_POST,'action',20);
                if ($action==='course') {
                    $title = ConsoleView::field($_POST,'title',150);
                    if ($title==='') throw new \InvalidArgumentException('Escribe el título del curso.');
                    $db->execute('INSERT INTO goldensite_academy_courses (title,description) VALUES (?,?)',[$title,self::text('description',3000)]);
                } elseif (in_array($action,['lesson','publish'],true)) {
                    $this->editCourse($db,self::id('course'),$action);
                } else {
                    throw new \InvalidArgumentException('Acción inválida.');
                }
                ConsoleView::audit('academy_'.$action);
                $_SESSION['academy_admin_notice'] = 'Academia actualizada.';
                header('Location: '.$base,true,303);
                return;
            } catch (\InvalidArgumentException $error) {
                http_response_code(422);
                $notice = $error->getMessage();
            }
        }
        $courses = $active?$db->fetchAll('SELECT c.*, (SELECT COUNT(*) FROM goldensite_academy_lessons l WHERE l.course_id=c.id) lessons FROM goldensite_academy_courses c ORDER BY c.id DESC LIMIT 100'):[];
        ConsoleView::render('academy',compact('active','base','notice','courses'),'Goldensite Academy');
    }

    private function editCourse(Database $db,int $course,string $action): void
    {
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $row = $db->fetch('SELECT published FROM goldensite_academy_courses WHERE id=? FOR UPDATE',[$course]);
            if (!$row || $row['published']) throw new \InvalidArgumentException('Selecciona un curso en borrador.');
            if ($action==='publish') {
                if (!$db->fetch('SELECT id FROM goldensite_academy_lessons WHERE course_id=? LIMIT 1',[$course])) {
                    throw new \InvalidArgumentException('Añade al menos una lección antes de publicar.');
                }
                $db->execute('UPDATE goldensite_academy_courses SET published=1 WHERE id=?',[$course]);
            } else {
                $title = ConsoleView::field($_POST,'title',150);
                $body = self::text('body',20000);
                $question = ConsoleView::field($_POST,'question',250);
                $answers = array_values(array_filter(array_map('trim',preg_split('/\R/u',self::text('answers',1200))),static fn($line)=>$line!==''));
                $correct = null;
                if ($question!=='') {
                    if (count($answers)<2 || count($answers)>6 || count(array_unique($answers))!==count($answers)) {
                        throw new \InvalidArgumentException('Escribe de 2 a 6 opciones distintas, una por línea.');
                    }
                    $correct = self::id('correct')-1;
                    if (!isset($answers[$correct])) throw new \InvalidArgumentException('La respuesta correcta no corresponde a una opción.');
                } elseif ($answers) {
                    throw new \InvalidArgumentException('Escribe la pregunta para esas opciones.');
                }
                if ($title==='' || $body==='') throw new \InvalidArgumentException('Completa el título y el contenido de la lección.');
                $db->execute('INSERT INTO goldensite_academy_lessons (course_id,title,body,question,answers,correct_index) VALUES (?,?,?,?,?,?)',
                    [$course,$title,$body,$question?:null,$answers?json_encode($answers,JSON_THROW_ON_ERROR):null,$correct]);
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public function publicPage(): void
    {
        header('Cache-Control: private, no-store');
        $active = FrontendEngine::isModuleActive('academy');
        if (!$active) http_response_code(404);
        $actor = Auth::id();
        $courseId = filter_var($_GET['course']??0,FILTER_VALIDATE_INT)?:0;
        $notice = $_SESSION['academy_notice']??'';
        unset($_SESSION['academy_notice']);
        $db = Database::getInstance();
        if ($active && $_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            if (!$actor) { header('Location: /login?return_to=%2Facademia',true,303); return; }
            try {
                $courseId = self::id('course');
                $action = ConsoleView::field($_POST,'action',20);
                if (!$db->fetch('SELECT id FROM goldensite_academy_courses WHERE id=? AND published=1',[$courseId])) {
                    throw new \InvalidArgumentException('Curso no disponible.');
                }
                if ($action==='enroll') {
                    $db->execute('INSERT INTO goldensite_academy_enrollments (course_id,user_id) VALUES (?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)',[$courseId,$actor]);
                    $_SESSION['academy_notice'] = 'Inscripción confirmada.';
                } elseif ($action==='complete') {
                    $this->complete($db,$courseId,$actor);
                    $_SESSION['academy_notice'] = 'Progreso guardado.';
                } else {
                    throw new \InvalidArgumentException('Acción inválida.');
                }
                header('Location: /academia?course='.$courseId,true,303);
                return;
            } catch (\InvalidArgumentException $error) {
                http_response_code(422);
                $notice = $error->getMessage();
            }
        }
        $courses = $active?$db->fetchAll('SELECT id,title,description FROM goldensite_academy_courses WHERE published=1 ORDER BY id DESC LIMIT 50'):[];
        $course = $active&&$courseId?$db->fetch('SELECT id,title,description FROM goldensite_academy_courses WHERE published=1 AND id=?',[$courseId]):null;
        $enrollment = $actor&&$course?$db->fetch('SELECT certificate,completed_at FROM goldensite_academy_enrollments WHERE course_id=? AND user_id=?',[$courseId,$actor]):null;
        $lessons = $enrollment?$db->fetchAll('SELECT l.id,l.title,l.body,l.question,l.answers,p.completed_at
            FROM goldensite_academy_lessons l LEFT JOIN goldensite_academy_progress p ON p.lesson_id=l.id AND p.user_id=?
            WHERE l.course_id=? ORDER BY l.id',[$actor,$courseId]):[];
        if ($active && $courseId && !$course && !$notice) { http_response_code(404); $notice='Curso no disponible.'; }
        $certificate = null;
        $verification = false;
        $this->render(compact('active','actor','notice','courses','course','enrollment','lessons','certificate','verification'));
    }

    private function complete(Database $db,int $course,int $actor): void
    {
        $lessonId = self::id('lesson');
        if (!RateLimiter::consume('academy-answer:'.$actor.':'.$lessonId,12,300)) throw new \InvalidArgumentException('Espera antes de volver a intentar.');
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $enrolled = $db->fetch('SELECT certificate FROM goldensite_academy_enrollments WHERE course_id=? AND user_id=? FOR UPDATE',[$course,$actor]);
            $lesson = $db->fetch('SELECT correct_index FROM goldensite_academy_lessons WHERE id=? AND course_id=?',[$lessonId,$course]);
            if (!$enrolled || !$lesson) throw new \InvalidArgumentException('Inscríbete en el curso para registrar tu progreso.');
            if ($lesson['correct_index']!==null && self::id('answer')-1!==(int)$lesson['correct_index']) {
                throw new \InvalidArgumentException('Respuesta incorrecta. Repasa la lección y vuelve a intentarlo.');
            }
            $db->execute('INSERT IGNORE INTO goldensite_academy_progress (lesson_id,user_id) VALUES (?,?)',[$lessonId,$actor]);
            if (!$enrolled['certificate'] && !$db->fetch('SELECT l.id FROM goldensite_academy_lessons l
                LEFT JOIN goldensite_academy_progress p ON p.lesson_id=l.id AND p.user_id=?
                WHERE l.course_id=? AND p.lesson_id IS NULL LIMIT 1',[$actor,$course])) {
                $user = Auth::user();
                $db->execute('UPDATE goldensite_academy_enrollments SET certificate=?,certificate_name=?,completed_at=UTC_TIMESTAMP() WHERE course_id=? AND user_id=?',
                    [bin2hex(random_bytes(24)),$user['name']?:$user['username'],$course,$actor]);
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public function verify(string $code): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
        $active = FrontendEngine::isModuleActive('academy');
        $certificate = $active&&preg_match('/^[a-f0-9]{48}$/D',$code)?Database::getInstance()->fetch('SELECT e.certificate_name,e.completed_at,c.title FROM goldensite_academy_enrollments e JOIN goldensite_academy_courses c ON c.id=e.course_id WHERE e.certificate=?',[$code]):null;
        if (!$certificate) http_response_code(404);
        $this->render(['verification'=>true,'certificate'=>$certificate]);
    }

    private function render(array $data): void
    {
        extract($data,EXTR_SKIP);
        $e = ConsoleView::escape(...);
        $csrfToken = Csrf::generate();
        $siteTitle = 'Goldensite Academy';
        ob_start();
        require dirname(__DIR__,2).'/views/frontend/academy.php';
        $content = ob_get_clean();
        require dirname(__DIR__,2).'/views/layouts/main.php';
    }

    private static function id(string $key): int
    {
        $id = filter_var($_POST[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
        if ($id===false) throw new \InvalidArgumentException('Selecciona una opción válida.');
        return $id;
    }

    private static function text(string $key,int $limit): string
    {
        $text = $_POST[$key]??'';
        if (!is_string($text) || strlen($text)>$limit || !mb_check_encoding($text,'UTF-8') || str_contains($text,"\0")) {
            throw new \InvalidArgumentException('El texto supera el límite permitido o contiene caracteres inválidos.');
        }
        return trim($text);
    }
}
