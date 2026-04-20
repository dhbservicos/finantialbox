<?php
namespace FFB\Helpers;

defined('ABSPATH') || exit;

/**
 * Paginação.
 * Arquivo separado para atender ao require_once do entry point
 * e ao autoloader PSR-4 (FFB\Helpers\Paginator → includes/Helpers/Paginator.php)
 */
class Paginator {
    public int $page;
    public int $perPage;
    public int $total;
    public int $totalPages;
    public int $offset;

    public function __construct(int $total, int $page = 1, int $perPage = 25) {
        $this->perPage    = max(1, min(100, $perPage));
        $this->total      = max(0, $total);
        $this->totalPages = (int)ceil($this->total / $this->perPage);
        $this->page       = max(1, min($page, max(1, $this->totalPages)));
        $this->offset     = ($this->page - 1) * $this->perPage;
    }

    public function hasPrevious(): bool { return $this->page > 1; }
    public function hasNext(): bool     { return $this->page < $this->totalPages; }
    public function from(): int         { return $this->total === 0 ? 0 : $this->offset + 1; }
    public function to(): int           { return min($this->offset + $this->perPage, $this->total); }

    public function render(string $baseUrl): string {
        if ($this->totalPages <= 1) return '';
        $html = '<nav><ul class="pagination pagination-sm mb-0">';

        $html .= $this->hasPrevious()
            ? '<li class="page-item"><a class="page-link" href="' . esc_url($this->pageUrl($baseUrl, $this->page - 1)) . '">‹</a></li>'
            : '<li class="page-item disabled"><span class="page-link">‹</span></li>';

        $start = max(1, $this->page - 2);
        $end   = min($this->totalPages, $this->page + 2);
        for ($i = $start; $i <= $end; $i++) {
            $html .= $i === $this->page
                ? "<li class=\"page-item active\"><span class=\"page-link\">{$i}</span></li>"
                : "<li class=\"page-item\"><a class=\"page-link\" href=\"" . esc_url($this->pageUrl($baseUrl, $i)) . "\">{$i}</a></li>";
        }

        $html .= $this->hasNext()
            ? '<li class="page-item"><a class="page-link" href="' . esc_url($this->pageUrl($baseUrl, $this->page + 1)) . '">›</a></li>'
            : '<li class="page-item disabled"><span class="page-link">›</span></li>';

        $html .= '</ul></nav>';
        return $html;
    }

    private function pageUrl(string $base, int $page): string {
        return add_query_arg('paged', $page, $base);
    }
}
