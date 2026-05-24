<?php

declare(strict_types=1);

namespace App\Controllers;

abstract class Controller
{
    protected function json(mixed $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function success(mixed $data = null, string $message = 'ok'): never
    {
        $this->json([
            'code' => 0,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ]);
    }

    protected function error(string $message, int $code = 400, int $statusCode = 400): never
    {
        $this->json([
            'code' => $code,
            'message' => $message,
            'data' => null,
            'timestamp' => time(),
        ], $statusCode);
    }

    protected function getInput(): array
    {
        $input = file_get_contents('php://input');
        if ($input === false || $input === '') {
            return $_POST;
        }

        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    protected function getQueryParams(): array
    {
        return $_GET;
    }

    protected function getHeader(string $name): ?string
    {
        $headers = getallheaders();
        $name = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $name) {
                return $value;
            }
        }
        return null;
    }

    protected function validateInput(array $input, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleList) {
            $ruleArray = is_array($ruleList) ? $ruleList : explode('|', $ruleList);
            $value = $input[$field] ?? null;

            foreach ($ruleArray as $rule) {
                $ruleParams = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $ruleParams = explode(',', $paramStr);
                }

                $error = $this->validateRule($field, $value, $rule, $ruleParams);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }
        }

        return $errors;
    }

    private function validateRule(string $field, mixed $value, string $rule, array $params): ?string
    {
        return match ($rule) {
            'required' => $value === null || $value === '' ? "{$field}不能为空" : null,
            'integer' => $value !== null && !filter_var($value, FILTER_VALIDATE_INT) ? "{$field}必须是整数" : null,
            'numeric' => $value !== null && !is_numeric($value) ? "{$field}必须是数字" : null,
            'email' => $value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL) ? "{$field}格式不正确" : null,
            'min' => $value !== null && strlen((string)$value) < (int)($params[0] ?? 0) ? "{$field}最少{$params[0]}个字符" : null,
            'max' => $value !== null && strlen((string)$value) > (int)($params[0] ?? 255) ? "{$field}最多{$params[0]}个字符" : null,
            'in' => $value !== null && !in_array($value, $params) ? "{$field}值不在允许范围内" : null,
            default => null,
        };
    }
}
