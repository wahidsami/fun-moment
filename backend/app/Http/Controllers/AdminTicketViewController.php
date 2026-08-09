<?php

namespace App\Http\Controllers;

use App\AdminNotification;
use App\Helpers\FlashMsg;
use App\SupportTicket;
use App\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTicketViewController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:ticket-list|ticket-view|ticket-delete',['only' => ['tickets']]);
        $this->middleware('permission:ticket-list|ticket-view',['only' => ['apiTickets', 'apiUpdateTicketStatus']]);
        $this->middleware('permission:ticket-view',['only' => ['ticketDetails']]);
        $this->middleware('permission:ticket-delete',['only' => ['ticketDelete']]);
    }

    public function tickets(){
        $all_tickets = SupportTicket::all();
        return view('backend.pages.ticket.tickets',compact('all_tickets'));
    }

    public function ticketDetails($id){

        $ticket_details = SupportTicket::findOrFail($id);
        $all_messages = SupportTicketMessage::where(['support_ticket_id'=>$id])->get();
        $q = $request->q ?? '';


        // admin notification
        $notification = AdminNotification::where('ticket_id', $id)->first();
        if (!empty($notification)){
            if ($notification->status == 0){
                AdminNotification::where('ticket_id', $id)->update(['status' => 1]);
            }
        }
        return view('backend.pages.ticket.ticket-details', compact('ticket_details','all_messages','q'));
    }

    public function apiTickets(): JsonResponse
    {
        $tickets = SupportTicket::with(['ticket_user', 'ticket_buyer', 'ticket_seller'])
            ->orderByDesc('id')
            ->take(100)
            ->get()
            ->map(function (SupportTicket $ticket) {
                return $this->formatTicketPayload($ticket);
            })->values();

        return response()->json([
            'status' => 'success',
            'tickets' => $tickets,
        ]);
    }

    public function apiUpdateTicketStatus(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::with(['ticket_user', 'ticket_buyer', 'ticket_seller'])->findOrFail($id);
        SupportTicket::where('id', $id)->update(['status' => $validated['status']]);

        return response()->json([
            'status' => 'success',
            'message' => __('Ticket status updated successfully'),
            'ticket' => $this->formatTicketPayload($ticket->fresh(['ticket_user', 'ticket_buyer', 'ticket_seller'])),
        ]);
    }

        public function ticketDelete($id=null)
        {
            SupportTicketMessage::where('support_ticket_id',$id)->delete();
            SupportTicket::find($id)->delete();
            return redirect()->back()->with(FlashMsg::item_new('Ticket Delete Success.'));
        }

    private function formatTicketPayload(SupportTicket $ticket): array
    {
        $user = $ticket->ticket_buyer ?? $ticket->ticket_seller ?? $ticket->ticket_user;
        $latestMessage = SupportTicketMessage::where('support_ticket_id', $ticket->id)
            ->orderByDesc('id')
            ->first();

        return [
            'id' => (int) $ticket->id,
            'user_name' => optional($user)->name ?? $ticket->title ?? __('Support User'),
            'user_email' => optional($user)->email ?? '',
            'subject_en' => $ticket->subject ?? $ticket->title ?? '',
            'subject_ar' => $ticket->subject ?? $ticket->title ?? '',
            'category' => $this->inferTicketCategory($ticket),
            'priority' => $ticket->priority ?: 'medium',
            'status' => $ticket->status ?: 'open',
            'created_at' => optional($ticket->created_at)->toDateString(),
            'last_message' => optional($latestMessage)->message ?? $ticket->description ?? '',
        ];
    }

    private function inferTicketCategory(SupportTicket $ticket): string
    {
        $haystack = strtolower(trim(implode(' ', array_filter([
            $ticket->title,
            $ticket->subject,
            $ticket->description,
        ]))));

        if (str_contains($haystack, 'refund') || str_contains($haystack, 'payment') || str_contains($haystack, 'wallet')) {
            return 'payment';
        }

        if (str_contains($haystack, 'technical') || str_contains($haystack, 'account') || str_contains($haystack, 'password') || str_contains($haystack, 'login') || str_contains($haystack, 'bug')) {
            return 'technical';
        }

        if (str_contains($haystack, 'service') || str_contains($haystack, 'order') || str_contains($haystack, 'seller') || str_contains($haystack, 'booking') || str_contains($haystack, 'delivery')) {
            return 'service';
        }

        return 'service';
    }
}
