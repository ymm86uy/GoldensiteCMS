<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum\Models;

use Modules\GoldensiteForum\Forum;

final class CategoryModel
{
    public static function all(): array
    {
        return Forum::db()->fetchAll('SELECT c.name category, c.slug category_slug, b.id, b.name, b.slug
            FROM goldensite_forum_categories c LEFT JOIN goldensite_forum_boards b ON b.category_id=c.id ORDER BY c.id,b.id');
    }

    public static function board(string $slug): ?array
    {
        return Forum::db()->fetch('SELECT b.*,c.name category,c.slug category_slug FROM goldensite_forum_boards b
            JOIN goldensite_forum_categories c ON c.id=b.category_id WHERE b.slug=?', [$slug]);
    }

    public static function add(string $category, string $board): void
    {
        Forum::transaction(static function () use ($category, $board): void {
            $db = Forum::db();
            $slug = Forum::slug($category);
            $db->execute('INSERT INTO goldensite_forum_categories (name,slug) VALUES (?,?) ON DUPLICATE KEY UPDATE id=id', [$category,$slug]);
            $parent = $db->fetch('SELECT id,name FROM goldensite_forum_categories WHERE slug=?', [$slug]);
            if ($parent['name'] !== $category) throw new \InvalidArgumentException('La categoría coincide con el identificador de otra existente.');
            try {
                $db->execute('INSERT INTO goldensite_forum_boards (category_id,name,slug) VALUES (?,?,?)',
                    [$parent['id'],$board,Forum::slug($board)]);
            } catch (\PDOException $error) {
                if (($error->errorInfo[1] ?? null) === 1062) throw new \InvalidArgumentException('Ya existe un subforo con esa dirección.');
                throw $error;
            }
        });
    }
}

