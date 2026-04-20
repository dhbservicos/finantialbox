<?php
namespace FFB\Helpers;

defined('ABSPATH') || exit;

/**
 * Validação centralizada.
 * Arquivo separado para atender ao require_once do entry point
 * e ao autoloader PSR-4 (FFB\Helpers\Validator → includes/Helpers/Validator.php)
 */
class Validator {
    private array $errors  = [];
    private array $cleaned = [];
    private array $data;

    public function __construct(array $data) { $this->data = $data; }

    public function required(string $field, string $label): static {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val === '') { $this->errors[$field] = "{$label} é obrigatório."; }
        else { $this->cleaned[$field] = $val; }
        return $this;
    }

    public function string(string $field, string $label, int $max = 255): static {
        if (isset($this->errors[$field])) return $this;
        $val = sanitize_text_field($this->data[$field] ?? '');
        if (mb_strlen($val) > $max) {
            $this->errors[$field] = "{$label} deve ter no máximo {$max} caracteres.";
        } else { $this->cleaned[$field] = $val; }
        return $this;
    }

    public function email(string $field, string $label): static {
        $val = sanitize_email($this->data[$field] ?? '');
        if (!is_email($val)) { $this->errors[$field] = "{$label} deve ser um e-mail válido."; }
        else { $this->cleaned[$field] = $val; }
        return $this;
    }

    public function positiveFloat(string $field, string $label): static {
        if (isset($this->errors[$field])) return $this;
        $raw = trim((string)($this->data[$field] ?? ''));

        // Detecta formato:
        // EN: "67.90" ou "1,234.56" (ponto como decimal, vírgula como milhar)
        // BR: "67,90" ou "1.234,56" (vírgula como decimal, ponto como milhar)
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $raw)) {
            // Formato BR com milhar: 1.234,56 → remove ponto, troca vírgula
            $raw = str_replace(['.', ','], ['', '.'], $raw);
        } elseif (str_contains($raw, ',') && !str_contains($raw, '.')) {
            // Apenas vírgula decimal: 67,90 → 67.90
            $raw = str_replace(',', '.', $raw);
        } else {
            // Formato EN (ponto decimal): "67.90", "1234.56" — usa direto
            // Remove vírgulas de milhar se houver: "1,234.56" → "1234.56"
            $raw = str_replace(',', '', $raw);
        }

        $val = filter_var($raw, FILTER_VALIDATE_FLOAT);
        if ($val === false || $val <= 0) {
            $this->errors[$field] = "{$label} deve ser um valor positivo.";
        } else {
            $this->cleaned[$field] = $val;
        }
        return $this;
    }

    public function date(string $field, string $label, string $format = 'Y-m-d'): static {
        if (isset($this->errors[$field])) return $this;
        $val = trim((string)($this->data[$field] ?? ''));
        $dt  = \DateTimeImmutable::createFromFormat($format, $val);
        if (!$dt || $dt->format($format) !== $val) {
            $this->errors[$field] = "{$label} deve ser uma data válida.";
        } else { $this->cleaned[$field] = $val; }
        return $this;
    }

    public function integer(string $field, string $label, int $min = 1): static {
        if (isset($this->errors[$field])) return $this;
        $val = filter_var($this->data[$field] ?? '', FILTER_VALIDATE_INT);
        if ($val === false || $val < $min) {
            $this->errors[$field] = "{$label} inválido.";
        } else { $this->cleaned[$field] = (int)$val; }
        return $this;
    }

    public function enum(string $field, string $label, array $allowed): static {
        if (isset($this->errors[$field])) return $this;
        $val = (string)($this->data[$field] ?? '');
        if (!in_array($val, $allowed, true)) {
            $this->errors[$field] = "{$label} possui valor inválido.";
        } else { $this->cleaned[$field] = $val; }
        return $this;
    }

    public function optional(string $field, mixed $default = null): static {
        $val = trim((string)($this->data[$field] ?? ''));
        $this->cleaned[$field] = $val !== '' ? sanitize_text_field($val) : $default;
        return $this;
    }

    public function minLength(string $field, string $label, int $min): static {
        if (isset($this->errors[$field])) return $this;
        $val = (string)($this->cleaned[$field] ?? $this->data[$field] ?? '');
        if (mb_strlen($val) < $min) {
            $this->errors[$field] = "{$label} deve ter no mínimo {$min} caracteres.";
        }
        return $this;
    }

    public function fails(): bool      { return !empty($this->errors); }
    public function errors(): array    { return $this->errors; }
    public function firstError(): string { return array_values($this->errors)[0] ?? ''; }
    public function validated(): array { return $this->cleaned; }
    public function get(string $field, mixed $default = null): mixed {
        return $this->cleaned[$field] ?? $default;
    }
}
