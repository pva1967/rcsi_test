<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date', $this->iso8601WithTimezone()],
            'ends_at' => ['required', 'date', $this->iso8601WithTimezone()],
        ];
    }

    private function iso8601WithTimezone(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-](?:[01]\d|2[0-3]):?[0-5]\d)$/D';

            if (! is_string($value) || preg_match($pattern, $value) !== 1) {
                $fail('Время должно быть в формате ISO 8601 и содержать смещение часового пояса.');
            }
        };
    }
}
