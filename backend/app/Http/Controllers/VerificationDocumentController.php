<?php

namespace App\Http\Controllers;

use App\SellerVerify;
use App\Services\ProviderVerificationDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerificationDocumentController extends Controller
{
    protected $documentService;

    public function __construct(ProviderVerificationDocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Authorized admin streaming of provider verification document.
     *
     * @param Request $request
     * @param int|string $sellerId
     * @param string $type national_id | license | cr
     */
    public function streamAdmin(Request $request, $sellerId, $type)
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            abort(401, __('Unauthenticated admin.'));
        }

        $verify = SellerVerify::where('seller_id', $sellerId)->first();
        if (!$verify) {
            abort(404, __('Provider verification profile not found.'));
        }

        $filename = $this->resolveDocumentFilename($verify, $type);
        if (empty($filename)) {
            abort(404, __('Document not found for this provider.'));
        }

        return $this->documentService->stream($filename, $admin);
    }

    /**
     * Authorized provider self-view streaming of own verification document.
     *
     * @param Request $request
     * @param string $type national_id | license | cr
     */
    public function streamProvider(Request $request, $type)
    {
        $user = Auth::guard('sanctum')->user() ?: Auth::guard('web')->user();
        if (!$user) {
            abort(401, __('Unauthenticated user.'));
        }

        $verify = SellerVerify::where('seller_id', $user->id)->first();
        if (!$verify) {
            abort(404, __('Verification profile not found.'));
        }

        $filename = $this->resolveDocumentFilename($verify, $type);
        if (empty($filename)) {
            abort(404, __('Document not found.'));
        }

        return $this->documentService->stream($filename, $user);
    }

    private function resolveDocumentFilename(SellerVerify $verify, string $type): ?string
    {
        switch ($type) {
            case 'national_id':
                return $verify->national_id_document;
            case 'license':
                return $verify->license_document;
            case 'cr':
                return $verify->cr_document;
            default:
                return null;
        }
    }
}
