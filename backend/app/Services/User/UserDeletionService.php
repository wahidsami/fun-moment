<?php

namespace App\Services\User;

use App\Accountdeactive;
use App\AdminAuditLog;
use App\Day;
use App\EditServiceHistory;
use App\MediaUpload;
use App\OnlineServiceFaq;
use App\Order;
use App\PayoutRequest;
use App\Report;
use App\ReportChatMessage;
use App\Review;
use App\Schedule;
use App\SellerVerify;
use App\Service;
use App\Serviceadditional;
use App\Servicebenifit;
use App\ServiceCoupon;
use App\Serviceinclude;
use App\SupportTicket;
use App\SupportTicketMessage;
use App\ToDoList;
use App\User;
use App\UserDeviceToken;
use App\UserTermsAcceptance;
use App\UserUniqueKey;
use App\Services\ProviderVerificationDocumentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\JobPost\Entities\BuyerJob;
use Modules\JobPost\Entities\JobRequest;
use Modules\LiveChat\Entities\LiveChatMessage;
use Modules\LiveChat\Entities\LiveChatConversation;
use Modules\Subscription\Entities\SellerSubscription;
use Modules\Subscription\Entities\SubscriptionHistory;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;

class UserDeletionService
{
    /**
     * Build a strictly read-only impact preview for administrative deletion.
     * Performs ZERO writes or session invalidations.
     *
     * @param User $user
     * @return array
     */
    public function buildImpactPreview(User $user): array
    {
        $role = (int) $user->user_type === User::USER_TYPE_SELLER ? 'seller' : 'buyer';
        $sellerType = 'n/a';
        if ($role === 'seller') {
            $sellerType = (int) $user->seller_type === User::SELLER_TYPE_COMPANY ? 'company' : 'individual';
        }

        // 1. Services & Offerings
        $totalServices = Service::where('seller_id', $user->id)->count();
        $activeServices = Service::where('seller_id', $user->id)->where('status', 1)->count();
        $schedulesCount = Schedule::where('seller_id', $user->id)->count();
        $daysCount = Day::where('seller_id', $user->id)->count();
        $couponsCount = ServiceCoupon::where('seller_id', $user->id)->count();
        $faqsCount = OnlineServiceFaq::where('seller_id', $user->id)->count();

        // 2. Orders & Bookings (Check both seller_id and buyer_id to be completely role-safe)
        $activeOrders = Order::where(function ($q) use ($user) {
            $q->where('seller_id', $user->id)->orWhere('buyer_id', $user->id);
        })->whereIn('status', [0, 1])->count();

        $retainedOrders = Order::where(function ($q) use ($user) {
            $q->where('seller_id', $user->id)->orWhere('buyer_id', $user->id);
        })->whereNotIn('status', [0, 1])->count();

        $totalOrders = $activeOrders + $retainedOrders;

        // 3. Financial & Wallet Records
        $walletBalance = 0.0;
        $walletHistoriesCount = 0;
        if (moduleExists('Wallet')) {
            $wallet = Wallet::where('user_id', $user->id)->orWhere('buyer_id', $user->id)->first();
            $walletBalance = $wallet ? (float) $wallet->balance : 0.0;
            $walletHistoriesCount = WalletHistory::where('user_id', $user->id)->orWhere('buyer_id', $user->id)->count();
        }

        $pendingPayouts = PayoutRequest::where('seller_id', $user->id)->where('status', 0)->count();
        $retainedPayouts = PayoutRequest::where('seller_id', $user->id)->where('status', '!=', 0)->count();

        $subscriptionsCount = 0;
        $subscriptionHistoryCount = 0;
        if (moduleExists('Subscription')) {
            $subscriptionsCount = SellerSubscription::where('seller_id', $user->id)->count();
            $subscriptionHistoryCount = SubscriptionHistory::where('seller_id', $user->id)->count();
        }

        // 4. Communication & Engagement
        $reviewsCount = Review::where('seller_id', $user->id)->orWhere('buyer_id', $user->id)->count();
        $supportTicketsCount = SupportTicket::where('seller_id', $user->id)
            ->orWhere('buyer_id', $user->id)
            ->orWhere('user_id', $user->id)
            ->count();

        $liveChatMessagesCount = 0;
        if (moduleExists('LiveChat')) {
            $liveChatMessagesCount = LiveChatMessage::where('seller_id', $user->id)->orWhere('buyer_id', $user->id)->count();
        }

        $reportsCount = Report::where('seller_id', $user->id)->orWhere('buyer_id', $user->id)->count();

        $buyerJobsCount = 0;
        if (moduleExists('JobPost')) {
            $buyerJobsCount = BuyerJob::where('buyer_id', $user->id)->count();
        }

        // 5. Auth, Security, Media & Compliance
        $sanctumTokensCount = $user->tokens()->count();
        $deviceTokensCount = UserDeviceToken::where('user_id', $user->id)->count();
        $termsAcceptancesCount = UserTermsAcceptance::where('user_id', $user->id)->count();
        $mediaUploadsCount = MediaUpload::where('user_id', $user->id)->count();

        $sellerVerify = SellerVerify::where('seller_id', $user->id)->first();
        $verificationStatus = 'none';
        $hasVerificationDocs = false;
        if ($sellerVerify) {
            $verificationStatus = (int) $sellerVerify->status === 1 ? 'verified' : ((int) $sellerVerify->status === 2 ? 'rejected' : 'pending');
            $hasVerificationDocs = !empty($sellerVerify->national_id_document) || !empty($sellerVerify->license_document) || !empty($sellerVerify->cr_document);
        }

        // 6. Detect Blocking Conditions
        $blockingReasons = [];
        if ($activeOrders > 0) {
            $blockingReasons[] = __('Active bookings or orders in progress (:count). All orders must be completed or cancelled before deleting the account.', ['count' => $activeOrders]);
        }
        if ($walletBalance > 0.001) {
            $blockingReasons[] = __('Outstanding unsettled wallet balance (:amount). Balance must be refunded or disbursed before deleting the account.', ['amount' => number_format($walletBalance, 2)]);
        }
        if ($pendingPayouts > 0) {
            $blockingReasons[] = __('Pending payout requests (:count) awaiting admin approval or payment. Payouts must be finalized or rejected first.', ['count' => $pendingPayouts]);
        }

        $canDelete = empty($blockingReasons);

        // 7. Determine Deletion Mode
        // If an account has zero financial/transaction records, it can be cleanly purged.
        // If an account has retained financial transactions (completed orders, wallet ledgers, payout records, subscription invoices),
        // it is anonymized and email is released to preserve financial integrity while enabling the owner to reuse the email.
        $totalRetainedFinancial = $retainedOrders + $walletHistoriesCount + $retainedPayouts + $subscriptionHistoryCount;
        $deletionMode = ($totalRetainedFinancial === 0) ? 'purge' : 'anonymize_and_release';

        // 8. Structure Impact Breakdown
        $permanentlyDeleted = [
            __('Seller schedules & availability days (:count)', ['count' => $schedulesCount + $daysCount]),
            __('Service coupons, FAQs & edit history (:count)', ['count' => $couponsCount + $faqsCount]),
            __('Sanctum authentication tokens & active sessions (:count)', ['count' => $sanctumTokensCount]),
            __('Mobile push device tokens (:count)', ['count' => $deviceTokensCount]),
            __('OTP verification codes, cooldown timers & password reset cache entries'),
            __('Support tickets, non-contract inquiries & report messages (:count)', ['count' => $supportTicketsCount + $reportsCount]),
            __('Private verification documents & identity files (:status)', ['status' => $hasVerificationDocs ? __('Attached') : __('None')]),
        ];

        if ($deletionMode === 'purge') {
            $permanentlyDeleted[] = __('User account table record (clean purge since no financial history exists)');
            if (moduleExists('Wallet')) {
                $permanentlyDeleted[] = __('Empty wallet container');
            }
        }

        $unpublishedOrDisabled = [
            __('Seller services unpublished & disabled (:count)', ['count' => $totalServices]),
            __('Account login disabled (user_status = 0, email_verified = 0, otp_verified = 0)'),
        ];

        $retainedRecords = [];
        if ($retainedOrders > 0) {
            $retainedRecords[] = __('Historical order records, invoices, and payment references (:count)', ['count' => $retainedOrders]);
        }
        if ($walletHistoriesCount > 0) {
            $retainedRecords[] = __('Wallet ledger transactions and financial history (:count)', ['count' => $walletHistoriesCount]);
        }
        if ($retainedPayouts > 0) {
            $retainedRecords[] = __('Processed payout transaction history (:count)', ['count' => $retainedPayouts]);
        }
        if ($subscriptionHistoryCount > 0) {
            $retainedRecords[] = __('Subscription billing invoices and history (:count)', ['count' => $subscriptionHistoryCount]);
        }
        if ($termsAcceptancesCount > 0) {
            $retainedRecords[] = __('Legal terms & privacy policy acceptance audit logs (:count)', ['count' => $termsAcceptancesCount]);
        }

        $anonymizedFields = [];
        if ($deletionMode === 'anonymize_and_release') {
            $anonymizedFields = [
                __('Full name replaced with generic identifier: "Deleted User #:id"', ['id' => $user->id]),
                __('Email address released for new registration (stored record renamed to deleted_:time_:id@deleted.local)'),
                __('Username scrambled (deleted_:id_:time)'),
                __('Phone number, avatar, address, and biography cleared'),
                __('OAuth identity links (Google, Apple, Facebook) detached'),
                __('Password scrambled with cryptographic random string'),
            ];
        }

        return [
            'can_delete' => $canDelete,
            'deletion_mode' => $deletionMode,
            'blocking_reasons' => $blockingReasons,
            'email_releasable' => true,
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $role,
                'seller_type' => $sellerType,
                'status' => (int) $user->user_status === 1 ? 'active' : 'inactive',
                'email_verified' => (bool) $user->email_verified,
                'otp_verified' => (bool) $user->otp_verified,
                'verification_status' => $verificationStatus,
                'created_at' => optional($user->created_at)->toIso8601String(),
            ],
            'counts' => [
                'services_count' => $totalServices,
                'active_services_count' => $activeServices,
                'schedules_count' => $schedulesCount,
                'days_count' => $daysCount,
                'coupons_count' => $couponsCount,
                'active_orders_count' => $activeOrders,
                'retained_orders_count' => $retainedOrders,
                'total_orders_count' => $totalOrders,
                'wallet_balance' => $walletBalance,
                'wallet_history_count' => $walletHistoriesCount,
                'pending_payouts_count' => $pendingPayouts,
                'retained_payouts_count' => $retainedPayouts,
                'subscriptions_count' => $subscriptionsCount,
                'subscription_history_count' => $subscriptionHistoryCount,
                'reviews_count' => $reviewsCount,
                'support_tickets_count' => $supportTicketsCount,
                'live_chat_messages_count' => $liveChatMessagesCount,
                'reports_count' => $reportsCount,
                'buyer_jobs_count' => $buyerJobsCount,
                'sanctum_tokens_count' => $sanctumTokensCount,
                'device_tokens_count' => $deviceTokensCount,
                'terms_acceptances_count' => $termsAcceptancesCount,
                'media_uploads_count' => $mediaUploadsCount,
            ],
            'breakdown' => [
                'permanently_deleted' => $permanentlyDeleted,
                'unpublished_or_disabled' => $unpublishedOrDisabled,
                'retained_records' => $retainedRecords,
                'anonymized_fields' => $anonymizedFields,
            ],
        ];
    }

    /**
     * Safely execute account deletion or anonymization within an atomic database transaction.
     * Revalidates blockers immediately prior to execution and releases email for reuse.
     *
     * @param User $user
     * @param int $adminId
     * @param string|null $confirmEmail
     * @return array
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function executeDeletion(User $user, int $adminId, ?string $confirmEmail = null): array
    {
        // 1. Target Account Confirmation
        if ($confirmEmail !== null) {
            $inputEmail = trim(strtolower($confirmEmail));
            $targetEmail = trim(strtolower($user->email));
            if ($inputEmail !== $targetEmail) {
                throw new \InvalidArgumentException(
                    __('Target confirmation email mismatch. Expected: :target, Provided: :input', [
                        'target' => $targetEmail,
                        'input' => $inputEmail,
                    ])
                );
            }
        }

        // 2. Server-side Revalidation
        $preview = $this->buildImpactPreview($user);
        if (!$preview['can_delete']) {
            throw new \RuntimeException(
                __('Deletion blocked: ') . implode(' ', $preview['blocking_reasons'])
            );
        }

        // 3. Atomic Database Execution
        return DB::transaction(function () use ($user, $adminId, $preview) {
            $originalEmail = $user->email;
            $originalUsername = $user->username;
            $originalName = $user->name;
            $originalId = $user->id;
            $mode = $preview['deletion_mode'];
            $role = $preview['account']['role'];

            // Step 1: Unpublish all services immediately
            Service::where('seller_id', $originalId)->update([
                'status' => 0,
                'is_service_on' => 0,
            ]);

            // Step 2: Delete seller-specific non-financial configuration
            Schedule::where('seller_id', $originalId)->delete();
            Day::where('seller_id', $originalId)->delete();
            ServiceCoupon::where('seller_id', $originalId)->delete();
            OnlineServiceFaq::where('seller_id', $originalId)->delete();
            EditServiceHistory::where('seller_id', $originalId)->delete();
            Servicebenifit::where('seller_id', $originalId)->delete();
            Serviceadditional::where('seller_id', $originalId)->delete();
            Serviceinclude::where('seller_id', $originalId)->delete();

            // Step 3: Remove private verification documents safely from disk and delete record
            $sellerVerify = SellerVerify::where('seller_id', $originalId)->first();
            if ($sellerVerify) {
                foreach (['national_id_document', 'license_document', 'cr_document'] as $docCol) {
                    $docFile = $sellerVerify->$docCol;
                    if (!empty($docFile)) {
                        try {
                            if (Storage::disk(ProviderVerificationDocumentService::DISK)->exists($docFile)) {
                                Storage::disk(ProviderVerificationDocumentService::DISK)->delete($docFile);
                            }
                        } catch (\Throwable $e) {
                            Log::warning("Could not delete verification document {$docFile}: " . $e->getMessage());
                        }
                    }
                }
                $sellerVerify->delete();
            }

            // Step 4: Revoke all Sanctum API personal access tokens
            $user->tokens()->delete();

            // Step 5: Delete device push notification tokens
            UserDeviceToken::where('user_id', $originalId)->delete();

            // Step 6: Delete support tickets and their messages
            $tickets = SupportTicket::where('seller_id', $originalId)
                ->orWhere('buyer_id', $originalId)
                ->orWhere('user_id', $originalId)
                ->get();
            foreach ($tickets as $ticket) {
                SupportTicketMessage::where('support_ticket_id', $ticket->id)->delete();
                $ticket->delete();
            }

            // Step 7: Delete live chat messages and conversations if module exists
            if (moduleExists('LiveChat')) {
                LiveChatMessage::where('seller_id', $originalId)->orWhere('buyer_id', $originalId)->delete();
                LiveChatConversation::where('seller_id', $originalId)->orWhere('buyer_id', $originalId)->delete();
            }

            // Step 8: Clean up reports and report chat messages (with report_id bugfix)
            $reports = Report::where('seller_id', $originalId)->orWhere('buyer_id', $originalId)->get();
            foreach ($reports as $report) {
                ReportChatMessage::where('report_id', $report->id)->delete();
                $report->delete();
            }

            // Step 9: Clean up buyer jobs if module exists
            if (moduleExists('JobPost')) {
                $jobs = BuyerJob::where('buyer_id', $originalId)->get();
                foreach ($jobs as $job) {
                    JobRequest::where('job_post_id', $job->id)->delete();
                    $job->delete();
                }
                JobRequest::where('seller_id', $originalId)->delete();
            }

            // Step 10: Clean up reviews & to-do lists
            Review::where('seller_id', $originalId)->orWhere('buyer_id', $originalId)->delete();
            ToDoList::where('user_id', $originalId)->delete();

            // Step 11: Purge OTP and authentication cache keys
            self::purgeAuthCaches($originalEmail);

            // Step 12: Record deactivation record
            Accountdeactive::updateOrCreate(
                ['user_id' => $originalId],
                [
                    'reason' => 'Administrative Deletion',
                    'description' => "Account deleted by Admin #{$adminId} (Mode: {$mode})",
                    'status' => 1,
                    'account_status' => 1,
                ]
            );

            // Step 13: Release Email and Apply Deletion Mode
            if ($mode === 'purge') {
                // Completely remove the clean test user row
                if (moduleExists('Wallet')) {
                    Wallet::where('user_id', $originalId)->delete();
                }
                UserUniqueKey::where('user_id', $originalId)->delete();
                $user->delete();
            } else {
                // Anonymize personal profile and release email
                $timestamp = time();
                $scrambledEmail = "deleted_{$timestamp}_{$originalId}@deleted.local";
                $scrambledUsername = "deleted_{$originalId}_{$timestamp}";

                $user->update([
                    'name' => "Deleted User #{$originalId}",
                    'email' => $scrambledEmail,
                    'username' => $scrambledUsername,
                    'phone' => null,
                    'image' => null,
                    'profile_background' => null,
                    'address' => null,
                    'about' => null,
                    'google_id' => null,
                    'apple_id' => null,
                    'facebook_id' => null,
                    'password' => Hash::make(Str::random(40)),
                    'user_status' => 0,
                    'email_verified' => 0,
                    'otp_verified' => 0,
                    'otp_code' => null,
                    'otp_expire_at' => null,
                ]);
            }

            // Step 14: Log Admin Audit Entry without leaking sensitive PII
            AdminAuditLog::record([
                'admin_id' => $adminId,
                'action' => 'user_delete',
                'resource_type' => 'user',
                'resource_id' => $originalId,
                'details_en' => "Admin #{$adminId} deleted {$role} account #{$originalId} ({$originalName}). Mode: {$mode}. Original email released.",
                'details_ar' => "قام المشرف #{$adminId} بحذف حساب ({$role}) #{$originalId} ({$originalName}). النمط: {$mode}. تم تحرير البريد الإلكتروني الأصلي.",
                'old_values' => [
                    'user_id' => $originalId,
                    'name' => $originalName,
                    'role' => $role,
                    'deletion_mode' => $mode,
                    'retained_orders' => $preview['counts']['retained_orders_count'],
                    'retained_wallet_history' => $preview['counts']['wallet_history_count'],
                ],
                'new_values' => [
                    'deletion_mode' => $mode,
                    'email_released' => true,
                    'released_email_domain' => substr(strrchr($originalEmail, "@"), 1),
                    'services_unpublished' => $preview['counts']['services_count'],
                ],
            ]);

            return [
                'status' => 'success',
                'message' => __('Account :name (ID :id) deleted successfully. Mode: :mode. Email :email has been released for new registration.', [
                    'name' => $originalName,
                    'id' => $originalId,
                    'mode' => $mode,
                    'email' => $originalEmail,
                ]),
                'mode' => $mode,
                'user_id' => $originalId,
                'released_email' => $originalEmail,
                'impact_summary' => $preview['counts'],
            ];
        });
    }

    /**
     * Purge OTP verifiers, cooldowns, and password reset cache keys associated with the email.
     *
     * @param string $email
     * @return void
     */
    public static function purgeAuthCaches(string $email): void
    {
        $variants = array_unique([trim($email), trim(strtolower($email))]);
        $actions = ['', '_register', '_login', '_password_reset', '_reset', '_verify'];

        foreach ($variants as $e) {
            $keys = [
                "pwd_reset_{$e}",
                "otp_attempts_{$e}",
            ];

            foreach ($actions as $act) {
                $keys[] = "otp_{$e}{$act}";
                $keys[] = "otp_cooldown_{$e}{$act}";
            }

            foreach ($keys as $k) {
                Cache::forget($k);
            }
        }
    }
}
