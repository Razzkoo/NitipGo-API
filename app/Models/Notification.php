<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'traveler_id',
        'role',
        'type',
        'title',
        'message',
        'icon',
        'action_url',
        'action_label',
        'notifiable_type',
        'notifiable_id',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read'  => 'boolean',
        'read_at'  => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->where('is_read', true);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTraveler(Builder $query, int $travelerId): Builder
    {
        return $query->where('traveler_id', $travelerId);
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // ─── Static Factories ─────────────────────────────────────────────────────

    /**
     * Kirim notifikasi ke user (admin atau customer).
     */
    public static function sendToUser(
        int    $userId,
        string $role,
        string $type,
        string $title,
        string $message,
        array  $options = []
    ): static {
        return static::create([
            'user_id'         => $userId,
            'role'            => $role,
            'type'            => $type,
            'title'           => $title,
            'message'         => $message,
            'icon'            => $options['icon']            ?? null,
            'action_url'      => $options['action_url']      ?? null,
            'action_label'    => $options['action_label']    ?? null,
            'notifiable_type' => $options['notifiable_type'] ?? null,
            'notifiable_id'   => $options['notifiable_id']   ?? null,
        ]);
    }

    /**
     * Kirim notifikasi ke traveler.
     */
    public static function sendToTraveler(
        int    $travelerId,
        string $type,
        string $title,
        string $message,
        array  $options = []
    ): static {
        return static::create([
            'traveler_id'     => $travelerId,
            'role'            => 'traveler',
            'type'            => $type,
            'title'           => $title,
            'message'         => $message,
            'icon'            => $options['icon']            ?? null,
            'action_url'      => $options['action_url']      ?? null,
            'action_label'    => $options['action_label']    ?? null,
            'notifiable_type' => $options['notifiable_type'] ?? null,
            'notifiable_id'   => $options['notifiable_id']   ?? null,
        ]);
    }

    /**
     * Kirim ke semua admin.
     */
    public static function sendToAllAdmins(
        string $type,
        string $title,
        string $message,
        array  $options = []
    ): void {
        $admins = User::where('role', 'admin')->pluck('id');
        foreach ($admins as $adminId) {
            static::sendToUser($adminId, 'admin', $type, $title, $message, $options);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update(['is_read' => true, 'read_at' => now()]);
        }
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'title'        => $this->title,
            'message'      => $this->message,
            'icon'         => $this->icon,
            'action_url'   => $this->action_url,
            'action_label' => $this->action_label,
            'is_read'      => $this->is_read,
            'read_at'      => $this->read_at?->format('d M Y, H:i'),
            'time'         => $this->created_at->diffForHumans(),
            'created_at'   => $this->created_at->format('d M Y, H:i'),
        ];
    }
}