<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class NotificationPreference extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'client_id',
        'email_enabled',
        'in_app_enabled',
        'push_enabled',
        'sms_enabled',
        'events',
        'email_frequency',
        'digest_enabled',
        'digest_time',
        'digest_days',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
        'dnd_enabled',
        'dnd_until'
    ];

    protected $casts = [
        'events' => 'array',
        'digest_days' => 'array',
        'email_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'digest_enabled' => 'boolean',
        'quiet_hours_enabled' => 'boolean',
        'dnd_enabled' => 'boolean',
        'dnd_until' => 'datetime',
    ];

    /**
     * Default event preferences
     */
    protected $attributes = [
        'events' => '{}',
        'email_enabled' => true,
        'in_app_enabled' => true,
        'push_enabled' => false,
        'sms_enabled' => false,
        'email_frequency' => 'immediate',
        'digest_enabled' => false,
        'quiet_hours_enabled' => false,
        'dnd_enabled' => false
    ];

    /**
     * Get default event settings
     */
    public static function getDefaultEvents(): array
    {
        return [
            'ticket_assigned' => ['email' => true, 'in_app' => true, 'push' => false, 'sms' => false],
            'ticket_updated' => ['email' => false, 'in_app' => true, 'push' => false, 'sms' => false],
            'comment_added' => ['email' => true, 'in_app' => true, 'push' => true, 'sms' => false],
            'ticket_resolved' => ['email' => true, 'in_app' => false, 'push' => false, 'sms' => false],
            'mention' => ['email' => true, 'in_app' => true, 'push' => true, 'sms' => false],
            'sla_breach' => ['email' => true, 'in_app' => true, 'push' => true, 'sms' => false],
            'ticket_escalated' => ['email' => true, 'in_app' => true, 'push' => true, 'sms' => false],
            'new_ticket' => ['email' => true, 'in_app' => true, 'push' => false, 'sms' => false],
        ];
    }

    /**
     * Initialize default events if empty
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->events)) {
                $model->events = self::getDefaultEvents();
            }
        });
    }

    /**
     * Check if a specific event and channel is enabled
     */
    public function isEventChannelEnabled(string $event, string $channel): bool
    {
        // Check if DND is enabled
        if ($this->dnd_enabled && (!$this->dnd_until || $this->dnd_until->isFuture())) {
            return false;
        }

        // Check if channel is globally enabled
        $channelKey = $channel . '_enabled';
        if (!$this->$channelKey) {
            return false;
        }

        // Check quiet hours for non-urgent notifications
        if ($this->quiet_hours_enabled && $channel !== 'in_app') {
            $now = now()->setTimezone($this->timezone);
            $start = $now->copy()->setTimeFromTimeString($this->quiet_hours_start);
            $end = $now->copy()->setTimeFromTimeString($this->quiet_hours_end);

            if ($end < $start) {
                // Quiet hours span midnight
                if ($now >= $start || $now < $end) {
                    return false;
                }
            } else {
                if ($now >= $start && $now < $end) {
                    return false;
                }
            }
        }

        // Check specific event settings
        $events = $this->events ?? [];
        if (isset($events[$event][$channel])) {
            return $events[$event][$channel];
        }

        // Default to enabled if not specified
        return true;
    }

    /**
     * Update event preference
     */
    public function updateEventPreference(string $event, string $channel, bool $enabled): void
    {
        $events = $this->events ?? [];

        if (!isset($events[$event])) {
            $events[$event] = [];
        }

        $events[$event][$channel] = $enabled;

        $this->update(['events' => $events]);
    }

    /**
     * Enable Do Not Disturb
     */
    public function enableDND($until = null): void
    {
        $this->update([
            'dnd_enabled' => true,
            'dnd_until' => $until
        ]);
    }

    /**
     * Disable Do Not Disturb
     */
    public function disableDND(): void
    {
        $this->update([
            'dnd_enabled' => false,
            'dnd_until' => null
        ]);
    }

    /**
     * Check if digest should be sent now
     */
    public function shouldSendDigestNow(): bool
    {
        if (!$this->digest_enabled) {
            return false;
        }

        $now = now()->setTimezone($this->timezone);
        $digestTime = $now->copy()->setTimeFromTimeString($this->digest_time);

        // Check if current time matches digest time (within 5 minute window)
        if (!$now->between($digestTime, $digestTime->copy()->addMinutes(5))) {
            return false;
        }

        // Check if today is in digest days
        if ($this->digest_days && !in_array($now->dayOfWeek, $this->digest_days)) {
            return false;
        }

        return true;
    }
}