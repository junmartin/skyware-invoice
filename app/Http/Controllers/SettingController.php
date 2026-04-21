<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\InvoiceSendingService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $sendMode = AppSetting::getValue('send_mode', 'manual');

        return view('settings.index', compact('sendMode'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'send_mode' => 'required|in:auto,manual',
        ]);

        AppSetting::setValue('send_mode', $data['send_mode']);

        return redirect()->route('settings.index')->with('status', 'Settings updated');
    }

    public function sendAllReadyNow(InvoiceSendingService $sendingService)
    {
        $count = $sendingService->sendReadyInvoices(auth()->id());

        return redirect()->route('settings.index')->with('status', 'Sent ready invoices: '.$count);
    }
}
