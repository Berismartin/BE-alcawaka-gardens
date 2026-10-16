<?php

namespace App\Http\Requests\Audit;

use App\Http\Requests\ListRequest;

class ListAuditLogsRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['created_at'];
    }

    protected function filters(): array
    {
        return [
            'subject_type' => ['string', 'max:40'],
            'subject_id' => ['string', 'max:64'],
            'action' => ['string', 'max:60'],
            'user_id' => ['integer'],
        ];
    }
}
