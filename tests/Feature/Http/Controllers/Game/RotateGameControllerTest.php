<?php

namespace Tests\Feature\Http\Controllers\Game;

use App\Events\GameRotation;
use App\Models\Expansion;
use App\Models\Game;
use App\Models\GameUser;
use App\Models\User;
use App\Models\GameBlackCards;
use App\Models\UserGameWhiteCards;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RotateGameControllerTest extends TestCase
{
    /** @var Game  */
    private $game;
    private GameService $gameService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gameService = new GameService();

        $this->game = Game::factory()->has(User::factory()->count(3))->create();
        foreach ($this->game->users as $user) {
            $this->gameService->drawWhiteCards($user, $this->game);
        }
        $this->game->judge_id = $this->game->users->first()->id;

        $this->gameService->drawBlackCard($this->game);
    }

    /** @test */
    public function rotating_changes_current_judge_to_new_user()
    {
        $blackCardPick = $this->game->currentBlackCard->pick;

        $firstJudge = $this->game->judge;
        $this->usersSelectCards($blackCardPick, $this->game);

        $this->actingAs($firstJudge)->postJson(route('api.game.rotate', $this->game->id))->assertOk();

        $this->game->refresh();
        $this->assertNotEquals($firstJudge->id, $this->game->judge_id);
    }

    /** @test */
    public function it_cycles_through_the_users_when_assigning_the_judge_when_number_of_users_is_odd()
    {
        $blackCardPick = $this->game->currentBlackCard->pick;

        $pickedJudgeIds = collect();

        $this->game->users->each(function ($user) use ($blackCardPick, $pickedJudgeIds) {

            $this->usersSelectCards($blackCardPick, $this->game);

            $this->actingAs($user)->postJson(route('api.game.rotate', $this->game->id))->assertOk();

            $this->game->refresh();

            $this->assertNotEquals($user->id, $this->game->judge->id);

            $pickedJudgeIds->add($this->game->judge_id);
        });


        $this->assertCount($this->game->users->count(), $pickedJudgeIds->unique()->all());
    }

    /** @test */
    public function it_cycles_through_the_users_when_assigning_the_judge_when_number_of_users_is_even()
    {
        $newUser = User::factory()->create();
        $this->game->users()->save($newUser);
        $blackCardPick = $this->game->currentBlackCard->pick;

        $pickedJudgeIds = collect();

        $this->game->refresh();
        $this->game->users->each(function ($user) use ($blackCardPick, $pickedJudgeIds) {

            $this->usersSelectCards($blackCardPick, $this->game);

            $this->actingAs($user)->postJson(route('api.game.rotate', $this->game->id))->assertOk();

            $this->game->refresh();

            $this->assertNotEquals($user->id, $this->game->judge->id);

            $pickedJudgeIds->add($this->game->judge_id);
        });


        $this->assertCount($this->game->users->count(), $pickedJudgeIds->unique()->all());
    }

    /** @test */
    public function it_gives_new_black_card_after_game_rotation()
    {
        $blackCardPick = $this->game->currentBlackCard->pick;
        $previousBlackCard = $this->game->currentBlackCard;

        $this->usersSelectCards($blackCardPick, $this->game);

        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('api.game.rotate', $this->game->id))->assertOk();

        $this->game->refresh();
        $this->assertNotEquals($this->game->currentBlackCard->id, $previousBlackCard->id);
    }

    /** @test */
    public function it_soft_deletes_all_submitted_white_cards()
    {
        $blackCardPick = $this->game->currentBlackCard->pick;
        $previousBlackCard = $this->game->currentBlackCard;

        $this->usersSelectCards($blackCardPick, $this->game);

        $selectedWhiteCards = UserGameWhiteCards::whereGameId($this->game->id)->where('selected', true)->get();

        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.game.rotate', $this->game->id))->assertOk();

        $selectedWhiteCards->each(fn ($selectedCard) => $this->assertSoftDeleted(UserGameWhiteCards::class, [
            'id' => $selectedCard->id,
        ]));
    }


    /** @test */
    public function it_emits_event_with_new_white_cards_after_game_rotation()
    {
        Event::fake();

        $this->game = Game::factory()->has(User::factory()->count(3))->create();
        foreach ($this->game->users as $user) {
            $this->gameService->drawWhiteCards($user, $this->game);
        }
        $this->game->judge_id = $this->game->users->first()->id;

        $this->gameService->drawBlackCard($this->game);

        $blackCardPick = $this->game->currentBlackCard->pick;

        $this->usersSelectCards($blackCardPick, $this->game);

        $this->actingAs($this->game->users->first())
            ->postJson(route('api.game.rotate', $this->game->id))
            ->assertOk();

        $this->game->users->each(function($user) use ($blackCardPick) {
            Event::assertDispatched(GameRotation::class, function (GameRotation $event) use ($blackCardPick, $user) {
                return
                    ($event->user->whiteCards->toArray() != null)
                    && Game::HAND_LIMIT === count($event->user->whiteCards->toArray())
                    && $event->game->id === $this->game->id
                    && $event->broadcastOn()->name === 'private-game.' . $this->game->id . '.' . $user->id;
            });
        });
    }
}