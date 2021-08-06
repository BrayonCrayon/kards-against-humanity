<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitCardRequest;
use App\Models\Game;
use App\Models\UserGameWhiteCards;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubmitCardsController extends Controller
{
    public function __invoke(SubmitCardRequest $request, Game $game): JsonResponse
    {
        $gameService = new GameService();
        $gameService->submitCards($request, $game);
        return response()->json();
    }
}
