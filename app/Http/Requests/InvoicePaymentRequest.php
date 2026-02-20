<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use App\Models\InvoicePayment;

class InvoicePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Auto-generate transaction ID before validation
     */
    protected function prepareForValidation()
    {
        // Only auto-generate for POST requests (create)
        if ($this->isMethod('post')) {
            $paymentMethod = $this->input('payment_method');
            
            // For UPI - if no transaction_id provided, generate one
            if ($paymentMethod === 'upi' && (!$this->has('transaction_id') || empty($this->transaction_id))) {
                $this->merge([
                    'transaction_id' => $this->generateUpiTransactionId(),
                ]);
            }
            // For Card - if no transaction_id provided, generate one
            elseif ($paymentMethod === 'card' && (!$this->has('transaction_id') || empty($this->transaction_id))) {
                $this->merge([
                    'transaction_id' => $this->generateCardTransactionId(),
                ]);
            }
            // For Cheque - if no transaction_id provided, generate one
            elseif ($paymentMethod === 'cheque' && (!$this->has('transaction_id') || empty($this->transaction_id))) {
                $this->merge([
                    'transaction_id' => $this->generateChequeTransactionId(),
                ]);
            }
            // For Bank Transfer - if no transaction_id provided, generate one
            elseif ($paymentMethod === 'bank_transfer' && (!$this->has('transaction_id') || empty($this->transaction_id))) {
                $this->merge([
                    'transaction_id' => $this->generateBankTransactionId(),
                ]);
            }
            // For Cash - if no transaction_id provided, generate one
            elseif ($paymentMethod === 'cash' && (!$this->has('transaction_id') || empty($this->transaction_id))) {
                $this->merge([
                    'transaction_id' => $this->generateCashTransactionId(),
                ]);
            }
        }
    }

    /**
     * Generate UPI Transaction ID
     */
    protected function generateUpiTransactionId(): string
    {
        $prefix = 'UPI';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(10));
        $timestamp = now()->timestamp;
        
        // Format: UPI-20240125-ABCDEFGHIJ-1706234567
        $transactionId = "{$prefix}-{$date}-{$random}-{$timestamp}";
        
        // Ensure uniqueness
        while ($this->transactionIdExists($transactionId)) {
            $random = strtoupper(Str::random(12));
            $transactionId = "{$prefix}-{$date}-{$random}-{$timestamp}";
        }
        
        return $transactionId;
    }

    /**
     * Generate Card Transaction ID
     */
    protected function generateCardTransactionId(): string
    {
        $prefix = 'CARD';
        $date = now()->format('Ymd');
        $random = mt_rand(1000, 9999); // Generate random 4 digits
        $timestamp = now()->timestamp;
        
        // Format: CARD-20240125-1234-1706234567
        $transactionId = "{$prefix}-{$date}-{$random}-{$timestamp}";
        
        // Ensure uniqueness
        while ($this->transactionIdExists($transactionId)) {
            $random = mt_rand(1000, 9999);
            $transactionId = "{$prefix}-{$date}-{$random}-{$timestamp}";
        }
        
        return $transactionId;
    }

    /**
     * Generate Cheque Transaction ID
     */
    protected function generateChequeTransactionId(): string
    {
        $prefix = 'CHQ';
        $date = now()->format('Ymd');
        $random = mt_rand(10000000, 99999999); // Generate random 8 digits cheque number
        $timestamp = now()->timestamp;
        
        // Format: CHQ-20240125-12345678-1706234567
        $transactionId = "{$prefix}-{$date}-{$random}-{$timestamp}";
        
        // Ensure uniqueness
        while ($this->transactionIdExists($transactionId)) {
            $random = mt_rand(10000000, 99999999);
            $transactionId = "{$prefix}-{$date}-{$random}-{$timestamp}";
        }
        
        return $transactionId;
    }

    /**
     * Generate Bank Transfer Transaction ID
     */
    protected function generateBankTransactionId(): string
    {
        $prefix = 'BANK';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(12));
        $timestamp = now()->timestamp;
        
        // Format: BANK-20240125-ABCDEFGHIJKL-1706234567
        $transactionId = "{$prefix}-{$date}-{$random}-{$timestamp}";
        
        // Ensure uniqueness
        while ($this->transactionIdExists($transactionId)) {
            $random = strtoupper(Str::random(14));
            $transactionId = "{$prefix}-{$date}-{$random}-{$timestamp}";
        }
        
        return $transactionId;
    }

    /**
     * Generate Cash Transaction ID
     */
    protected function generateCashTransactionId(): string
    {
        $prefix = 'CASH';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(8));
        $timestamp = now()->timestamp;
        
        // Format: CASH-20240125-ABCDEFGH-1706234567
        $transactionId = "{$prefix}-{$date}-{$random}-{$timestamp}";
        
        // Ensure uniqueness
        while ($this->transactionIdExists($transactionId)) {
            $random = strtoupper(Str::random(10));
            $transactionId = "{$prefix}-{$date}-{$random}-{$timestamp}";
        }
        
        return $transactionId;
    }

    /**
     * Check if transaction ID already exists
     */
    protected function transactionIdExists($transactionId): bool
    {
        return InvoicePayment::where('transaction_id', $transactionId)
            ->when($this->route('id'), function ($query, $id) {
                return $query->where('id', '!=', $id);
            })
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,upi,cheque,card',
            'transaction_id' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => 'nullable|string|max:500',
        ];

        // Add unique rule only for POST (create) and if transaction_id is provided
        if ($this->isMethod('post') && $this->has('transaction_id') && !empty($this->transaction_id)) {
            $rules['transaction_id'][] = 'unique:invoice_payments,transaction_id';
        }

        // For PUT/PATCH (update), ignore current record
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $id = $this->route('id');
            if ($id && $this->has('transaction_id') && !empty($this->transaction_id)) {
                $rules['transaction_id'][] = 'unique:invoice_payments,transaction_id,' . $id;
            }
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check if payment amount exceeds invoice balance
            if ($this->invoice_id && $this->amount) {
                $invoice = \App\Models\Invoice::find($this->invoice_id);
                
                if ($invoice) {
                    $balanceAmount = $invoice->balance_amount ?? $invoice->total_amount;
                    
                    if ($this->amount > $balanceAmount) {
                        $validator->errors()->add(
                            'amount',
                            'Payment amount (₹' . number_format($this->amount, 2) . 
                            ') exceeds invoice balance (₹' . number_format($balanceAmount, 2) . ')'
                        );
                    }
                }
            }

            // Only validate format if user has provided a transaction ID
            // If auto-generated, skip format validation
            if ($this->has('transaction_id') && !empty($this->transaction_id)) {
                $paymentMethod = $this->payment_method;
                $transactionId = $this->transaction_id;
                
                // Check if this is auto-generated or user-provided
                $isAutoGenerated = $this->isAutoGeneratedTransactionId($transactionId);
                
                // Only validate format for user-provided transaction IDs
                if (!$isAutoGenerated) {
                    // Validate UPI ID format for UPI payments (only if user provided)
                    if ($paymentMethod === 'upi') {
                        if (!preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9]+$/', $transactionId)) {
                            $validator->errors()->add(
                                'transaction_id',
                                'Please enter a valid UPI ID (e.g., name@bank)'
                            );
                        }
                    }

                    // Validate card last 4 digits for card payments (only if user provided)
                    if ($paymentMethod === 'card') {
                        if (!preg_match('/^\d{4}$/', $transactionId)) {
                            $validator->errors()->add(
                                'transaction_id',
                                'Please enter the last 4 digits of the card'
                            );
                        }
                    }

                    // Validate cheque number for cheque payments (only if user provided)
                    if ($paymentMethod === 'cheque') {
                        if (!preg_match('/^\d{6,8}$/', $transactionId)) {
                            $validator->errors()->add(
                                'transaction_id',
                                'Please enter a valid cheque number (6-8 digits)'
                            );
                        }
                    }

                    // Validate bank transaction reference (only if user provided)
                    if ($paymentMethod === 'bank_transfer') {
                        if (strlen($transactionId) < 4) {
                            $validator->errors()->add(
                                'transaction_id',
                                'Please enter a valid bank transaction reference'
                            );
                        }
                    }
                }
            }
        });
    }

    /**
     * Check if transaction ID is auto-generated
     */
    protected function isAutoGeneratedTransactionId($transactionId): bool
    {
        // Check if transaction ID follows auto-generated pattern
        $patterns = [
            '/^UPI-\d{8}-[A-Z0-9]{10,12}-\d{10}$/',  // UPI auto-generated
            '/^CARD-\d{8}-\d{4}-\d{10}$/',           // Card auto-generated
            '/^CHQ-\d{8}-\d{8}-\d{10}$/',            // Cheque auto-generated
            '/^BANK-\d{8}-[A-Z0-9]{12,14}-\d{10}$/', // Bank auto-generated
            '/^CASH-\d{8}-[A-Z0-9]{8,10}-\d{10}$/',  // Cash auto-generated
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $transactionId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'invoice_id' => 'invoice',
            'amount' => 'payment amount',
            'payment_date' => 'payment date',
            'payment_method' => 'payment method',
            'transaction_id' => 'transaction ID',
            'notes' => 'notes',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Please select an invoice',
            'invoice_id.exists' => 'Selected invoice does not exist',
            'amount.required' => 'Payment amount is required',
            'amount.numeric' => 'Payment amount must be a number',
            'amount.min' => 'Payment amount must be at least :min',
            'payment_date.required' => 'Payment date is required',
            'payment_date.date' => 'Please enter a valid payment date',
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Please select a valid payment method',
            'transaction_id.unique' => 'This transaction ID has already been used',
            'transaction_id.max' => 'Transaction ID cannot exceed :max characters',
            'notes.max' => 'Notes cannot exceed :max characters',
        ];
    }

    /**
     * Handle failed validation
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}