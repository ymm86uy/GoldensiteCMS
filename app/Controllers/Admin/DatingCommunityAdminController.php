<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,ConsoleView,Database,DatingMedia,DatingService,IdentityAccess,LicenseMiddleware,ModuleCatalog,Money,RateLimiter,RequestGuard,SecretStore};

final class DatingCommunityAdminController
{
    public function index(): void
    {
        Auth::requireAdmin(); if (!IdentityAccess::superuser()) { http_response_code(403); return; }
        LicenseMiddleware::enforce('dating',ModuleCatalog::get('dating')['name'],ModuleCatalog::price('dating')); $db=Database::getInstance(); $notice=$_SESSION['dating_admin_notice']??null; unset($_SESSION['dating_admin_notice']);
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try {
            $action=ConsoleView::field($_POST,'action',32); $id=filter_var($_POST['profile_id']??0,FILTER_VALIDATE_INT)?:0;
            if (in_array($action,['approve_profile','reject_profile','suspend','restore'],true)) {
                $pdo=$db->getConnection(); $pdo->beginTransaction();
                try {
                    $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$id]); $profile=DatingService::member($id); if (!$profile) throw new \InvalidArgumentException('Perfil no disponible.');
                    if ($action==='approve_profile') { if ($profile['account_status']==='deleted'||$profile['moderation_hold']||(int)$profile['actual_age']<18) throw new \InvalidArgumentException('El perfil está cerrado, suspendido o no cumple el requisito de edad.'); $db->execute("UPDATE goldensite_dating_accounts SET review_state='approved' WHERE profile_id=?",[$id]); }
                    elseif ($action==='reject_profile') $db->execute("UPDATE goldensite_dating_accounts SET review_state='rejected' WHERE profile_id=?",[$id]);
                    elseif ($action==='suspend') $db->execute("UPDATE goldensite_dating_accounts SET moderation_hold=1,status=IF(status='deleted','deleted','suspended') WHERE profile_id=?",[$id]);
                    else $db->execute("UPDATE goldensite_dating_accounts SET moderation_hold=0,status=IF(status='deleted','deleted','paused'),review_state='pending' WHERE profile_id=?",[$id]);
                    $pdo->commit();
                } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
                ConsoleView::audit('dating_'.$action,'profile:'.$id); $notice='Revisión del perfil guardada. Suspender no elimina la cuenta general del CMS.';
            } elseif ($action==='photo_review') {
                $photo=ConsoleView::field($_POST,'photo_id',32); $decision=ConsoleView::field($_POST,'decision',12); if (!in_array($decision,['approved','rejected'],true)) throw new \InvalidArgumentException('Decisión inválida.');
                $row=$db->fetch("SELECT profile_id FROM goldensite_dating_photos WHERE id=? AND status<>'deleted'",[$photo]); if (!$row||!is_file(DatingMedia::path($photo))) throw new \InvalidArgumentException('La foto no está disponible.');
                $pdo=$db->getConnection(); $pdo->beginTransaction();
                try {
                    $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$row['profile_id']]);
                    $current=$db->fetch("SELECT id FROM goldensite_dating_photos WHERE id=? AND status<>'deleted'",[$photo]); $member=DatingService::member((int)$row['profile_id']); if (!$current||!$member||$member['account_status']==='deleted') throw new \InvalidArgumentException('El perfil o la foto ya se retiraron.');
                    $db->execute('UPDATE goldensite_dating_photos SET status=? WHERE id=?',[$decision,$photo]);
                    if ($decision==='approved') $db->execute('UPDATE goldensite_dating_accounts SET avatar_photo_id=? WHERE profile_id=? AND avatar_photo_id IS NULL',[$photo,$row['profile_id']]);
                    else $db->execute('UPDATE goldensite_dating_accounts SET avatar_photo_id=NULL WHERE profile_id=? AND avatar_photo_id=?',[$row['profile_id'],$photo]);
                    $pdo->commit();
                } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
                ConsoleView::audit('dating_photo_reviewed',$photo.' / '.$decision); $notice='Moderación de la foto guardada. Aprobar una foto no certifica identidad documental.';
            } elseif ($action==='report_review') {
                $report=filter_var($_POST['report_id']??0,FILTER_VALIDATE_INT)?:0; $state=ConsoleView::field($_POST,'report_state',16); $note=ConsoleView::field($_POST,'moderator_note',1000);
                if (!in_array($state,['resolved','dismissed'],true)||$note==='') throw new \InvalidArgumentException('Indica resolución y nota de moderación.');
                $db->execute('UPDATE goldensite_dating_safety_reports SET status=?,moderator_note=?,reviewed_by=? WHERE id=?',[$state,$note,Auth::id(),$report]); ConsoleView::audit('dating_report_reviewed','report:'.$report); $notice='Denuncia revisada. Las sanciones se aplican desde la ficha del perfil.';
            } elseif ($action==='hide_story') {
                $story=filter_var($_POST['story_id']??0,FILTER_VALIDATE_INT)?:0; $db->execute('UPDATE goldensite_dating_stories SET is_hidden=1 WHERE id=?',[$story]); ConsoleView::audit('dating_story_hidden','story:'.$story); $notice='Historia retirada del muro.';
            } elseif ($action==='grant_credits') {
                if (!RateLimiter::consume('dating-admin-credit:'.Auth::id(),5,300)) throw new \InvalidArgumentException('Espera antes de repetir la operación.'); ConsoleView::password();
                $amount=Money::cents(ConsoleView::field($_POST,'amount',12)); $reference=ConsoleView::field($_POST,'reference',120); $reason=ConsoleView::field($_POST,'reason',255);
                if ($amount<1||strlen($reference)<4||strlen($reason)<10) throw new \InvalidArgumentException('Indica créditos positivos, referencia única y motivo.');
                $pdo=$db->getConnection(); $pdo->beginTransaction();
                try {
                    $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$id]); $member=DatingService::member($id); if (!$member||!in_array($member['account_status'],['active','paused'],true)) throw new \InvalidArgumentException('El perfil no admite créditos.');
                    $prior=$db->fetch('SELECT * FROM goldensite_dating_adjustments WHERE reference=?',[$reference]);
                    if ($prior) { if ((int)$prior['profile_id']!==$id||Money::cents($prior['amount'])!==$amount) throw new \InvalidArgumentException('La referencia ya existe con otro importe o perfil.'); }
                    else {
                        $db->execute('INSERT INTO goldensite_dating_adjustments (id,profile_id,reference,amount,reason,actor_id) VALUES (?,?,?,?,?,?)',[bin2hex(random_bytes(16)),$id,$reference,Money::decimal($amount),$reason,Auth::id()]);
                        $db->execute('UPDATE goldensite_dating_profiles SET wallet_balance=wallet_balance+? WHERE id=? AND wallet_balance<=99999999.99-?',[Money::decimal($amount),$id,Money::decimal($amount)]);
                        if ((int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn()!==1) throw new \InvalidArgumentException('Se superaría el límite del saldo virtual.');
                        $db->execute("INSERT INTO goldensite_dating_transactions (user_id,type,amount,description) VALUES (?,'credito_manual',?,?)",[$id,Money::decimal($amount),$reason.' / '.$reference]);
                    }
                    $pdo->commit();
                } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
                ConsoleView::audit('dating_virtual_credits_granted','profile:'.$id.' / '.$reference); $notice='Créditos virtuales registrados una sola vez. Esta operación no registra ni confirma un cobro bancario.';
            } elseif ($action==='save_gift') {
                $name=ConsoleView::field($_POST,'gift_name',100); $price=Money::cents(ConsoleView::field($_POST,'gift_price',12)); if ($name===''||$price<1) throw new \InvalidArgumentException('Indica nombre y coste positivo en créditos.');
                $db->execute("INSERT INTO goldensite_dating_gifts (name,price,icon,status) VALUES (?,?,'🎁',1)",[$name,Money::decimal($price)]); $notice='Regalo añadido al catálogo de créditos virtuales.';
            } elseif ($action==='gift_state') {
                $gift=filter_var($_POST['gift_id']??0,FILTER_VALIDATE_INT)?:0; $state=ConsoleView::field($_POST,'gift_state',1); if (!in_array($state,['0','1'],true)) throw new \InvalidArgumentException('Estado inválido.'); $db->execute('UPDATE goldensite_dating_gifts SET status=? WHERE id=?',[(int)$state,$gift]); $notice='Disponibilidad del regalo actualizada.';
            } else throw new \InvalidArgumentException('Acción desconocida.');
            $_SESSION['dating_admin_notice']=$notice; header('Location: /admin/dating'); return;
        } catch (\InvalidArgumentException $e) { http_response_code(422); $notice=$e->getMessage(); } }
        $profiles=$db->fetchAll('SELECT p.id,p.username,p.bio,p.interests,p.wallet_balance,a.user_id,a.status,a.review_state,a.email_verified_at,a.avatar_photo_id,a.moderation_hold,TIMESTAMPDIFF(YEAR,a.birth_date,UTC_DATE()) age FROM goldensite_dating_accounts a JOIN goldensite_dating_profiles p ON p.id=a.profile_id ORDER BY a.updated_at DESC,p.id DESC LIMIT 100');
        $photos=$db->fetchAll("SELECT ph.*,p.username FROM goldensite_dating_photos ph JOIN goldensite_dating_profiles p ON p.id=ph.profile_id WHERE ph.status='pending' ORDER BY ph.created_at LIMIT 60");
        $reports=$db->fetchAll("SELECT r.*,p.username target_name FROM goldensite_dating_safety_reports r JOIN goldensite_dating_profiles p ON p.id=r.target_id WHERE r.status='open' ORDER BY r.created_at LIMIT 50"); foreach ($reports as &$report) if ($report['message_evidence']) $report['message_evidence']=SecretStore::open($report['message_evidence']); unset($report);
        ConsoleView::render('dating_community',['notice'=>$notice,'profiles'=>$profiles,'photos'=>$photos,'reports'=>$reports,'gifts'=>$db->fetchAll('SELECT * FROM goldensite_dating_gifts ORDER BY id DESC LIMIT 100'),'stories'=>$db->fetchAll('SELECT id,caption,photo_id FROM goldensite_dating_stories WHERE expires_at>NOW() AND is_hidden=0 ORDER BY id DESC LIMIT 30'),'legacyCount'=>(int)$db->fetch('SELECT COUNT(*) n FROM goldensite_dating_profiles WHERE id NOT IN (SELECT profile_id FROM goldensite_dating_accounts)')['n']],'Goldensite Love Dating');
    }
}
