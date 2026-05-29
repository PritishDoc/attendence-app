<?php
/**
 * Input Validation Helper
 */

class Validator {

    private $errors = [];

    public function required(string $field, $value): self {
        if (empty($value) && $value !== '0') {
            $this->errors[$field] = "$field is required";
        }
        return $this;
    }

    public function email(string $field, $value): self {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "Invalid email address";
        }
        return $this;
    }

    public function minLength(string $field, $value, int $min): self {
        if (!empty($value) && strlen($value) < $min) {
            $this->errors[$field] = "$field must be at least $min characters";
        }
        return $this;
    }

    public function maxLength(string $field, $value, int $max): self {
        if (!empty($value) && strlen($value) > $max) {
            $this->errors[$field] = "$field must not exceed $max characters";
        }
        return $this;
    }

    public function numeric(string $field, $value): self {
        if (!empty($value) && !is_numeric($value)) {
            $this->errors[$field] = "$field must be a number";
        }
        return $this;
    }

    public function inArray(string $field, $value, array $allowed): self {
        if (!empty($value) && !in_array($value, $allowed)) {
            $this->errors[$field] = "$field must be one of: " . implode(', ', $allowed);
        }
        return $this;
    }

    public function phone(string $field, $value): self {
        if (!empty($value) && !preg_match('/^[\+]?[0-9\-\s\(\)]{7,20}$/', $value)) {
            $this->errors[$field] = "Invalid phone number";
        }
        return $this;
    }

    public function latitude(string $field, $value): self {
        if (!empty($value) && ($value < -90 || $value > 90)) {
            $this->errors[$field] = "Invalid latitude";
        }
        return $this;
    }

    public function longitude(string $field, $value): self {
        if (!empty($value) && ($value < -180 || $value > 180)) {
            $this->errors[$field] = "Invalid longitude";
        }
        return $this;
    }

    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function validate(): void {
        if ($this->hasErrors()) {
            Response::error('Validation failed', 422, $this->errors);
        }
    }
}

/**
 * Sanitize input string
 */
function sanitizeInput($data) {
    if (is_string($data)) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

/**
 * Get JSON body from request
 */
function getRequestBody(): array {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

/**
 * Get a value from request body with optional default
 */
function getParam(array $body, string $key, $default = null) {
    return isset($body[$key]) ? sanitizeInput($body[$key]) : $default;
}
