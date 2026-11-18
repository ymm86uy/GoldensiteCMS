<?php
declare(strict_types=1);
namespace App\Core;

/** Chooses a complete Forum implementation while the core keeps routes and storage. */
final class ForumDistribution
{
    public const ID='addon-forum';
    public const FILES=[
        'Appearance.php','Attachments.php','Forum.php','Markdown.php','Notifications.php','Reputation.php',
        'Assets/forum.css','Assets/forum.js',
        'Controllers/ForumController.php','Controllers/ReplyController.php','Controllers/TopicController.php',
        'Controllers/Admin/ModerationController.php',
        'Models/CategoryModel.php','Models/ReplyModel.php','Models/TopicModel.php',
        'Views/appearance.php','Views/attachments.php','Views/author.php','Views/board.php','Views/create.php',
        'Views/editor.php','Views/error.php','Views/inbox-link.php','Views/index.php','Views/meta.php',
        'Views/moderation.php','Views/notifications.php','Views/topic.php',
    ];
    private const PREFIX='Modules\\GoldensiteForum\\';

    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (!str_starts_with($class,self::PREFIX)) return;
            $name=str_replace('\\','/',substr($class,strlen(self::PREFIX))).'.php';
            if (!in_array($name,self::FILES,true)) return;
            $root=self::verifiedRoot();
            if ($root!==null) require $root.'/forum/'.$name;
        },true,true);
    }

    private static function verifiedRoot(): ?string
    {
        static $checked=false,$root=null;
        if ($checked) return $root;
        $checked=true;
        $item=ExtensionPackages::installed()[self::ID]??null;
        if (($item['module']['native']??null)!=='forum' || ($item['use_bundled']??false)) return null;
        try {
            $claim=ExtensionPackages::claim($item['code']);
            if ($claim['module']!==$item['module'] || $claim['sha256']!==$item['sha256']
                || version_compare(ReleaseUpdate::version(),$claim['module']['min_core'],'<')) {
                throw new \RuntimeException('El manifiesto activo no coincide o requiere otro núcleo.');
            }
            $directory=ExtensionPackages::directory().'/'.$claim['sha256'];
            $base=realpath($directory);
            if (!$base || is_link($directory)) throw new \RuntimeException('La carpeta activa no está disponible.');
            $archive=$directory.'/package.zip';
            $zip=new \ZipArchive();
            if (is_link($archive) || !is_file($archive) || !hash_equals($claim['sha256'],hash_file('sha256',$archive))
                || $zip->open($archive,\ZipArchive::RDONLY)!==true) throw new \RuntimeException('El ZIP activo no coincide con su firma.');
            try {
                foreach (self::FILES as $name) {
                    $relative='forum/'.$name;
                    $path=$directory.'/'.$relative;
                    $real=realpath($path);
                    $contents=$zip->getFromName($relative);
                    if (!$real || str_replace('\\','/',$real)!==str_replace('\\','/',$base).'/'.$relative
                        || !is_file($path) || !is_string($contents) || !hash_equals(hash('sha256',$contents),hash_file('sha256',$path))) {
                        throw new \RuntimeException('No coincide la integridad de '.$name.'.');
                    }
                }
            } finally { $zip->close(); }
            return $root=$directory;
        } catch (\Throwable $error) {
            error_log('GoldenSiteCMS Forum: se usa el módulo incluido. '.$error->getMessage());
            return null;
        }
    }

    public static function projectRoot(): string { return dirname(__DIR__,2); }
}
