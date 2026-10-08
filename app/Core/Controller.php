<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller with shared helpers for views and request input.
 */
abstract class Controller
{
    /**
     * Render a view within the layout.
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        View::render($view, $data, $layout);
    }

    /**
     * Return a JSON response.
     *
     * @param array<string, mixed> $data
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /**
     * Read a sanitized value from the current request ($_POST then $_GET).
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Validate that the CSRF token in the request matches the session token.
     */
    protected function verifyCsrf(): void
    {
        $token = (string) ($_POST['_token'] ?? '');
        if (!Auth::verifyCsrf($token)) {
            abort(419, 'Your session expired. Please try again.');
        }
    }

    /**
     * Basic required-field validation. Returns array of error messages keyed by field.
     *
     * @param array<int, string> $fields
     * @return array<string, string>
     */
    protected function requireFields(array $fields): array
    {
        $labels = [
            'name' => 'Full Name',
            'email' => 'Email',
            'password' => 'Password',
            'password_confirm' => 'Confirm Password',
            'valid_id' => 'Valid government ID',
            'credential' => 'Credential document',
            'credential_type' => 'Credential type',
        ];
        $errors = [];
        foreach ($fields as $field) {
            if (trim((string) $this->input($field, '')) === '') {
                $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
                $errors[$field] = $label . ' is required.';
            }
        }
        return $errors;
    }
}
