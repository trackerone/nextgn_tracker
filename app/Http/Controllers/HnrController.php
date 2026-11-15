<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HnrAccount;
use App\Models\HnrObligation;
use App\Models\Peer;
use App\Services\Hnr\HnrService;
use App\Services\Hnr\HnrSettings;
use App\Services\Settings\TrackerRuntimeSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class HnrController extends Controller
{
    public function index(Request $request, HnrSettings $settings, HnrService $hnr, TrackerRuntimeSettings $tracker): Response
    {
        $user = $request->user();

        return response()->view('hnr.account', [
            'rules' => $settings->values(),
            'exempt' => $settings->exempt($user),
            'restricted' => $hnr->restricted($user),
            'overdue' => $hnr->overdueCount($user),
            'state' => HnrAccount::query()->where('user_id', $user->id)->first(),
            'obligations' => HnrObligation::query()->where('user_id', $user->id)->with('torrent')->latest()->paginate(15),
            'activeIds' => Peer::query()->where('user_id', $user->id)->where('last_announce_at', '>', now()->subMinutes($tracker->ghostTimeout()))->pluck('torrent_id')->all(),
            'events' => DB::table('hnr_events')->where('user_id', $user->id)->latest('id')->limit(20)->get(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
