<?php

namespace Tests\Feature\Game;

use App\Models\Expansion;
use App\Models\User;
use Illuminate\Http\Response;
use Tests\TestCase;

class CreateGameTest extends TestCase
{

    /** @test */
    public function it_does_not_allow_empty_user_name()
    {
        $expansionIds = Expansion::take(3)->get()->pluck('id');
        $this->postJson(route('api.game.store'), [
            'userName' => "",
            'expansionIds' => $expansionIds->toArray()
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @test */
    public function it_does_not_allow_user_to_select_no_expansions()
    {
        $user = User::factory()->make();
        $this->postJson(route('api.game.store'), [
            'userName'   => $user->name,
            'expansionIds' => []
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @test */
    public function it_does_not_allow_undefined_expansions()
    {
        $user = User::factory()->make();
        $this->postJson(route('api.game.store'), [
            'userName'   => $user->name,
            'expansionIds' => [-1]
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @test */
    public function it_creates_user()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::take(1)->get()->pluck('id');
        $this->postJson(route('api.game.store'), [
            'userName'   => $userName,
            'expansionIds' => $expansionIds->toArray()
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'name' => $userName
        ]);
    }

    /** @test */
    public function it_creates_game()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::take(1)->get()->pluck('id');
        $response = $this->postJson(route('api.game.store'), [
            'userName'   => $userName,
            'expansionIds' => $expansionIds->toArray()
        ])->assertOk()
        ->getOriginalContent();

        $this->assertDatabaseHas('games', [
            'id' => $response['game']->id,
            'name' => $response['game']->name
        ]);
    }

    /** @test */
    public function it_assigns_users_when_game_is_created()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::first()->pluck('id');
        $response = $this->postJson(route('api.game.store'), [
            'userName'   => $userName,
            'expansionIds' => $expansionIds->toArray()
        ])->assertOk()
            ->getOriginalContent();

        $this->assertDatabaseHas('game_users', [
            'game_id' => $response['game']->id,
            'user_id' => $response['user']->id
        ]);
    }

    /** @test */
    public function it_gives_users_cards_when_a_game_is_created()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::first()->pluck('id');

        $this->postJson(route('api.game.store'), [
            'userName' => $userName,
            'expansionIds' => $expansionIds
        ])->assertOk();
        $createdUser = User::where('name', $userName)->first();

        $this->assertCount(7, $createdUser->whiteCards);
    }

    /** @test */
    public function it_assigns_selected_expansions_when_game_is_created()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::take(1)->get()->pluck('id');
        $response = $this->postJson(route('api.game.store'), [
            'userName'   => $userName,
            'expansionIds' => $expansionIds->toArray()
        ])->assertOk()
            ->getOriginalContent();

        $expansionIds->each(fn ($id) =>
            $this->assertDatabaseHas('game_expansions', [
                'game_id' => $response['game']->id,
                'expansion_id' => $id
            ])
        );
    }

    /** @test */
    public function it_expects_certain_shape()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::take(1)->get()->pluck('id');
        $this->postJson(route('api.game.store'), [
            'userName'   => $userName,
            'expansionIds' => $expansionIds->toArray()
        ])->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name'
                ],
                'game' => [
                    'id',
                    'name'
                ],
                'cards' => [
                    'id',
                    'text',
                    'expansion_id'
                ],
            ]);
    }
}
