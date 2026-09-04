<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lead extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'owner_id', 'client_id',
        'company_name', 'contact_person', 'phone', 'email',
        'website', 'country', 'city',
        'source', 'product_interest', 'budget', 'currency', 'notes',
        'status', 'lost_reason', 'expected_close_date',
        'po_id', 'invoice_id','org_id', 'custom_fields'
    ];

    protected $casts = [
        'budget'              => 'decimal:2',
        'expected_close_date' => 'date',
        'custom_fields'       => 'array',
    ];

    public function user()        { return $this->belongsTo(User::class); }
    public function owner()       { return $this->belongsTo(User::class, 'owner_id'); }
    public function client()      { return $this->belongsTo(Client::class); }
    public function activities()  { return $this->hasMany(LeadActivity::class)->latest(); }
    public function followUps()   { return $this->hasMany(LeadFollowUp::class)->orderBy('due_date'); }
    public function pendingFollowUps() {
        return $this->hasMany(LeadFollowUp::class)->where('is_done', false)->orderBy('due_date');
    }
    public function campaigns() {
        return $this->belongsToMany(Campaign::class, 'campaign_leads')
            ->withPivot('assigned_to')->withTimestamps();
    }
    public function leadProducts()   { return $this->hasMany(LeadProduct::class); }
    public function quotations()     { return $this->hasMany(Quotation::class); }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'po_id'); }
    public function invoice()       { return $this->belongsTo(Invoice::class, 'invoice_id'); }

    public function isWon(): bool  { return $this->status === 'closed_won'; }
    public function isLost(): bool { return $this->status === 'closed_lost'; }
    public function isOpen(): bool { return !in_array($this->status, ['closed_won', 'closed_lost']); }

}