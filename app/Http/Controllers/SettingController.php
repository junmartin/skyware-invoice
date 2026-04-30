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
        $defaultRecipientEmail = AppSetting::getValue('default_recipient_email', '');

        return view('settings.index', compact('sendMode', 'defaultRecipientEmail'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'send_mode' => 'required|in:auto,manual',
            'default_recipient_email' => 'nullable|email',
        ]);

        AppSetting::setValue('send_mode', $data['send_mode']);
        AppSetting::setValue('default_recipient_email', $data['default_recipient_email'] ?? null);

        return redirect()->route('settings.index')->with('status', 'Settings updated');
    }

    public function sendAllReadyNow(InvoiceSendingService $sendingService)
    {
        $count = $sendingService->sendReadyInvoices(auth()->id());

        return redirect()->route('settings.index')->with('status', 'Sent ready invoices: '.$count);
    }
}
