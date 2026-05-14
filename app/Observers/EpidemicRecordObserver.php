<?php

namespace App\Observers;

use App\Models\EpidemicRecord;

class EpidemicRecordObserver
{
    /**
     * Handle the EpidemicRecord "created" event.
     */
    public function created(EpidemicRecord $epidemicRecord): void
    {
        //
    }

    /**
     * Handle the EpidemicRecord "updated" event.
     */
    public function updated(EpidemicRecord $epidemicRecord): void
    {
        if ($epidemicRecord->wasChanged('cases')) {
            \App\Models\EpidemicRecordAudit::create([
                'epidemic_record_id' => $epidemicRecord->id,
                'old_cases' => $epidemicRecord->getOriginal('cases'),
                'new_cases' => $epidemicRecord->cases,
                'reason' => 'Sync/API Correction',
            ]);
        }
    }

    /**
     * Handle the EpidemicRecord "deleted" event.
     */
    public function deleted(EpidemicRecord $epidemicRecord): void
    {
        //
    }

    /**
     * Handle the EpidemicRecord "restored" event.
     */
    public function restored(EpidemicRecord $epidemicRecord): void
    {
        //
    }

    /**
     * Handle the EpidemicRecord "force deleted" event.
     */
    public function forceDeleted(EpidemicRecord $epidemicRecord): void
    {
        //
    }
}
