<?php

namespace App\Http\Requests;

use App\ValueObjects\Filter;
use App\ValueObjects\FilterCollection;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class FilterableRequest extends FormRequest
{
    /**
     * @var FilterCollection|null
     */
    private ?FilterCollection $filterCollection = null;

    /**
     * @var array Fields that are allowed to be filtered.
     * Override this in child classes to define allowed fields.
     */
    protected array $filterableFields = [];

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filters' => 'sometimes|array',
            'filters.*.field' => 'required|string',
            'filters.*.operator' => [
                'required',
                'string',
                'in:' . implode(',', Filter::getSupportedOperators())
            ],
            'filters.*.value' => 'sometimes',
        ];
    }

    /**
     * We prepare the request for validation rules, when
     * the data is json.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $filters = $this->query('filters');

        if (is_string($filters)) {
            $decoded = json_decode($filters, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([
                    'filters' => $decoded
                ]);
            }
        }
    }

    /**
     * Get the parsed FilterCollection from the request.
     *
     * @return FilterCollection
     */
    public function getFilters(): FilterCollection
    {
        if ($this->filterCollection !== null) {
            return $this->filterCollection;
        }

        $filtersData = $this->input('filters', []);

        // Handle JSON string if passed in query params
        if (is_string($filtersData)) {
            $filtersData = json_decode($filtersData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Invalid JSON format for filters parameter.');
            }
        }

        if (empty($filtersData)) {
            $this->filterCollection = new FilterCollection();
            return $this->filterCollection;
        }

        try {
            $this->filterCollection = FilterCollection::fromArray($filtersData);
            $this->validateFilterableFields($this->filterCollection);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Error parsing filters: ' . $e->getMessage());
        }

        return $this->filterCollection;
    }

    /**
     * Validate that all filter fields are in the allowed list.
     *
     * @param FilterCollection $filters
     * @throws InvalidArgumentException
     */
    protected function validateFilterableFields(FilterCollection $filters): void
    {
        // If no filterable fields are defined, skip validation (allow all fields)
        if (empty($this->filterableFields)) {
            return;
        }

        foreach ($filters->all() as $filter) {
            if (!in_array($filter->getField(), $this->filterableFields, true)) {
                throw new InvalidArgumentException(
                    "Field '{$filter->getField()}' is not allowed for filtering. " .
                    "Allowed fields: " . implode(', ', $this->filterableFields)
                );
            }
        }
    }

    public function getValueByField(string $field): mixed
    {
        $filter = array_filter($this->getFilters()->all(), function ($f) use ($field) {
            return $f->getField() === $field;
        });

        if (empty($filter)) {
            return null;
        }

        return $filter[0]->getValue();
    }

    public function remove(string $field): void
    {
        $filters = $this->getFilters()->all();

        $filtered = array_filter($filters, function ($filter) use ($field) {
            return $filter->getField() !== $field;
        });

        $filtered = array_values($filtered);

        $this->filterCollection = new FilterCollection($filtered);
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'filters' => 'filtros',
            'filters.*.field' => 'campo del filtro',
            'filters.*.operator' => 'operador del filtro',
            'filters.*.value' => 'valor del filtro',
        ];
    }

    /**
     * Get custom error messages for validator.
     */
    public function messages(): array
    {
        return [
            'filters.array' => 'Los filtros deben ser un array.',
            'filters.*.field.required' => 'El campo del filtro es requerido.',
            'filters.*.field.string' => 'El campo del filtro debe ser texto.',
            'filters.*.operator.required' => 'El operador del filtro es requerido.',
            'filters.*.operator.string' => 'El operador del filtro debe ser texto.',
            'filters.*.operator.in' => 'El operador del filtro no es válido. Operadores permitidos: ' .
                implode(', ', Filter::getSupportedOperators()),
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST)
        );
    }
}
