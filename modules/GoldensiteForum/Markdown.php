<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum;

use App\Core\ConsoleView;

final class Markdown
{
    public static function render(string $text): string
    {
        $parts = preg_split('/^```([a-zA-Z0-9+-]{0,24})[ \t]*\R([\s\S]*?)^```[ \t]*(?:\R|$)/m', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';
        foreach ($parts as $index=>$part) {
            if ($index%3===1) continue;
            if ($index%3===2) {
                $language = strtolower($parts[$index-1]);
                $code = ConsoleView::escape($part);
                if (in_array($language,['php','js','javascript','sql','python','json','css'],true)) {
                    $code = preg_replace('/\b(function|return|const|let|class|public|private|select|where|from|def|import|true|false|null)\b/i','<span class="gs-forum-keyword">$1</span>',$code);
                }
                $html .= '<pre><code>'.$code.'</code></pre>';
                continue;
            }
            foreach (preg_split('/\R{2,}/u',trim($part)) as $paragraph) {
                if ($paragraph==='') continue;
                $escaped = ConsoleView::escape($paragraph);
                $escaped = preg_replace_callback('/`([^`\r\n]+)`|\*\*([^*\r\n]+)\*\*|(?<!\*)\*([^*\r\n]+)\*|(?<![\w@])(@[\p{L}\p{N}_.-]+)/u', static function ($m) {
                    if (($m[1]??'')!=='') return '<code>'.$m[1].'</code>';
                    if (($m[2]??'')!=='') return '<strong>'.$m[2].'</strong>';
                    if (($m[3]??'')!=='') return '<em>'.$m[3].'</em>';
                    return '<span class="gs-forum-mention">'.$m[4].'</span>';
                }, $escaped);
                $html .= '<p>'.nl2br($escaped,false).'</p>';
            }
        }
        return $html;
    }
}

