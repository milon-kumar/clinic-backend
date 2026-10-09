<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContentPage extends Model
{
    /** Slugs that would clash with existing site routes. */
    public const RESERVED_SLUGS = ['admin', 'api', 'login', 'signup', 'account', 'cart', 'checkout', 'booking', 'payment'];

    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'is_published',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public static function normalizeSlug(?string $slug, ?string $fallback = null): string
    {
        return Str::slug((string) ($slug ?: $fallback));
    }

    private const ALLOWED_TAGS = [
        'p', 'br', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'strong', 'b', 'em', 'i', 'u',
        'blockquote', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    /**
     * Strip everything except simple formatting so stored content is safe to render.
     */
    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('root');
        if (! $root) {
            return e(strip_tags($html));
        }
        self::cleanNode($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function cleanNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'form', 'head'], true)) {
                $node->removeChild($child);
                continue;
            }

            self::cleanNode($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap unknown tags (div, span, font...) but keep their text/children.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $keep = $tag === 'a' && in_array($attr->name, ['href', 'target', 'rel'], true);
                if (! $keep) {
                    $child->removeAttributeNode($attr);
                }
            }

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if ($href !== '' && ! preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $href)) {
                    $child->removeAttribute('href');
                }
                if ($child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $child->removeAttribute('target');
                    $child->removeAttribute('rel');
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(bool $withContent = true): array
    {
        $row = [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'metaTitle' => $this->meta_title,
            'metaDescription' => $this->meta_description,
            'isPublished' => (bool) $this->is_published,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
        if ($withContent) {
            $row['content'] = $this->content ?? '';
        }

        return $row;
    }
}
