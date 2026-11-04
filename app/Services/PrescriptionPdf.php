<?php

namespace App\Services;

use App\Models\Prescription;
use Barryvdh\DomPDF\Facade\Pdf;

class PrescriptionPdf
{
    public function render(Prescription $prescription): string
    {
        $prescription->loadMissing(['items', 'patient', 'location', 'visit']);

        return Pdf::loadView('pdf.prescription', ['prescription' => $prescription])->setPaper('a4')->output();
    }

    public function filename(Prescription $prescription): string
    {
        return 'prescription-'.$prescription->id.'.pdf';
    }
}
