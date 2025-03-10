<?php

namespace YourVendor\LaravelAIBridge\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AICompletion extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ai_completions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'provider',
        'model',
        'prompt',
        'completion',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'user_id',
        'request_data',
        'response_data',
        'execution_time',
        'status',
        'error',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'execution_time' => 'float',
        'request_data' => 'array',
        'response_data' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Create a new AI completion record for successful generations.
     *
     * @param string $provider
     * @param string $model
     * @param string $prompt
     * @param string $completion
     * @param array $options
     * @return static
     */
    public static function createSuccess(
        string $provider,
        string $model,
        string $prompt,
        string $completion,
        array $options = []
    ): self {
        $record = new static([
            'provider' => $provider,
            'model' => $model,
            'prompt' => $prompt,
            'completion' => $completion,
            'status' => 'success',
        ]);

        if (isset($options['prompt_tokens'])) {
            $record->prompt_tokens = $options['prompt_tokens'];
        }

        if (isset($options['completion_tokens'])) {
            $record->completion_tokens = $options['completion_tokens'];
        }

        if (isset($options['total_tokens'])) {
            $record->total_tokens = $options['total_tokens'];
        } elseif (isset($options['prompt_tokens'], $options['completion_tokens'])) {
            $record->total_tokens = $options['prompt_tokens'] + $options['completion_tokens'];
        }

        if (isset($options['user_id'])) {
            $record->user_id = $options['user_id'];
        }

        if (isset($options['request_data'])) {
            $record->request_data = $options['request_data'];
        }

        if (isset($options['response_data'])) {
            $record->response_data = $options['response_data'];
        }

        if (isset($options['execution_time'])) {
            $record->execution_time = $options['execution_time'];
        }

        if (isset($options['metadata'])) {
            $record->metadata = $options['metadata'];
        }

        $record->save();

        return $record;
    }

    /**
     * Create a new AI completion record for failed generations.
     *
     * @param string $provider
     * @param string $model
     * @param string $prompt
     * @param string $error
     * @param array $options
     * @return static
     */
    public static function createError(
        string $provider,
        string $model,
        string $prompt,
        string $error,
        array $options = []
    ): self {
        $record = new static([
            'provider' => $provider,
            'model' => $model,
            'prompt' => $prompt,
            'error' => $error,
            'status' => 'error',
        ]);

        if (isset($options['user_id'])) {
            $record->user_id = $options['user_id'];
        }

        if (isset($options['request_data'])) {
            $record->request_data = $options['request_data'];
        }

        if (isset($options['response_data'])) {
            $record->response_data = $options['response_data'];
        }

        if (isset($options['execution_time'])) {
            $record->execution_time = $options['execution_time'];
        }

        if (isset($options['metadata'])) {
            $record->metadata = $options['metadata'];
        }

        $record->save();

        return $record;
    }

    /**
     * Query to get completions from a specific provider.
     *
     * @param string $provider
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function fromProvider(string $provider)
    {
        return static::query()->where('provider', $provider);
    }

    /**
     * Query to get completions using a specific model.
     *
     * @param string $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function usingModel(string $model)
    {
        return static::query()->where('model', $model);
    }

    /**
     * Query to get successful completions.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function successful()
    {
        return static::query()->where('status', 'success');
    }

    /**
     * Query to get failed completions.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function failed()
    {
        return static::query()->where('status', 'error');
    }

    /**
     * Scope a query to get completions created within a specified timeframe.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|string $start
     * @param \Carbon\Carbon|string|null $end
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCreatedBetween($query, $start, $end = null)
    {
        if (is_string($start)) {
            $start = Carbon::parse($start);
        }

        $query->where('created_at', '>=', $start);

        if ($end) {
            if (is_string($end)) {
                $end = Carbon::parse($end);
            }
            $query->where('created_at', '<=', $end);
        }

        return $query;
    }

    /**
     * Scope a query to get completions for a specific user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get token usage summary (total tokens used, cost, etc.)
     *
     * @param \Illuminate\Database\Eloquent\Builder|null $query
     * @return array
     */
    public static function getTokenUsageSummary($query = null)
    {
        $query = $query ?: static::query();

        $results = $query->selectRaw('
            SUM(prompt_tokens) as total_prompt_tokens,
            SUM(completion_tokens) as total_completion_tokens,
            SUM(total_tokens) as total_tokens,
            COUNT(*) as request_count,
            COUNT(DISTINCT DATE(created_at)) as days_active
        ')->first();

        return [
            'total_prompt_tokens' => $results->total_prompt_tokens ?? 0,
            'total_completion_tokens' => $results->total_completion_tokens ?? 0,
            'total_tokens' => $results->total_tokens ?? 0,
            'request_count' => $results->request_count ?? 0,
            'days_active' => $results->days_active ?? 0,
            'average_tokens_per_request' => $results->request_count > 0
                ? ($results->total_tokens / $results->request_count)
                : 0,
        ];
    }

    /**
     * Create the ai_completions table migration.
     *
     * @return string
     */
    public static function getMigration(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_completions', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('model')->index();
            $table->text('prompt');
            $table->text('completion')->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->float('execution_time')->nullable();
            $table->string('status')->default('success')->index();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_completions');
    }
};
PHP;
    }
}