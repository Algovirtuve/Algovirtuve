<?php

namespace Database\Seeders;

use App\Enums\RecipeStatus;
use App\Models\Administrator;
use App\Models\Recipe;
use App\Models\Request as AdminRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class RequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $administrators = $this->seedAdministrators();

        $this->seedWaitingRequests($administrators, count: 12);
        $this->seedReviewedRequests($administrators, status: RecipeStatus::Accepted, count: 6);
        $this->seedReviewedRequests($administrators, status: RecipeStatus::Declined, count: 6);
    }

    /**
     * @return Collection<int, Administrator>
     */
    private function seedAdministrators(int $count = 2): Collection
    {
        $administratorUsers = User::factory()->count($count)->create();

        return $administratorUsers
            ->map(fn (User $user): Administrator => Administrator::factory()->createOne([
                'user_id' => $user->id,
            ]))
            ->values();
    }

    /**
     * @param  Collection<int, Administrator>  $administrators
     */
    private function seedWaitingRequests(Collection $administrators, int $count): void
    {
        $requesters = User::factory()->count($count)->create();

        foreach ($requesters as $requester) {
            $recipe = Recipe::factory()->for($requester, 'owner')->createOne([
                'status' => RecipeStatus::Draft,
            ]);

            AdminRequest::factory()->createOne([
                'user_id' => $requester->id,
                'recipe_id' => $recipe->id,
                'administrator_id' => null,
                'date' => now()->subDays(random_int(0, 21))->toDateString(),
            ]);
        }
    }

    /**
     * @param  Collection<int, Administrator>  $administrators
     */
    private function seedReviewedRequests(Collection $administrators, RecipeStatus $status, int $count): void
    {
        $requesters = User::factory()->count($count)->create();

        foreach ($requesters as $requester) {
            $recipe = Recipe::factory()->for($requester, 'owner')->createOne([
                'status' => $status,
            ]);

            /** @var Administrator $administrator */
            $administrator = $administrators->random();

            AdminRequest::factory()->createOne([
                'user_id' => $requester->id,
                'recipe_id' => $recipe->id,
                'administrator_id' => $administrator->id,
                'date' => now()->subDays(random_int(0, 21))->toDateString(),
            ]);
        }
    }
}
