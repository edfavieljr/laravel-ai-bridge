<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Traits;

use Edfavieljr\LaravelAIBridge\Facades\AI;
use Edfavieljr\LaravelAIBridge\Testing\AIFake;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;
use Edfavieljr\LaravelAIBridge\Traits\HasAICapabilities;
use Illuminate\Database\Eloquent\Model;

class FakeModel extends Model
{
    use HasAICapabilities;

    protected $fillable = ['title', 'body', 'description'];
    public $exists = false;
}

class ConfiguredModel extends Model
{
    use HasAICapabilities;

    protected $fillable = ['title'];
    public $exists = false;

    protected function aiConfig(): array
    {
        return [
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-20250514',
        ];
    }
}

class HasAICapabilitiesTest extends TestCase
{
    protected FakeModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new FakeModel([
            'title' => 'Test Title',
            'body' => 'This is a test body with some content.',
            'description' => 'A short description',
        ]);
    }

    // --- New ai*() methods ---

    public function test_ai_generate_text(): void
    {
        $fake = AI::fake(['generateText' => 'AI response']);

        $result = $this->model->aiGenerateText('body');

        $this->assertEquals('AI response', $result);
        $fake->assertCalled('generateText');
    }

    public function test_ai_generate_text_with_template(): void
    {
        $fake = AI::fake(['generateText' => 'Summary']);

        $this->model->aiGenerateText('body', 'Summarize: %s');

        $fake->assertCalledWith('generateText', function (string $prompt) {
            return str_contains($prompt, 'Summarize:');
        });
    }

    public function test_ai_generate_text_returns_empty_for_null(): void
    {
        $model = new FakeModel(['title' => null]);
        $result = $model->aiGenerateText('title');
        $this->assertEquals('', $result);
    }

    public function test_ai_embed(): void
    {
        $fake = AI::fake(['generateEmbeddings' => [[0.1, 0.2]]]);

        $result = $this->model->aiEmbed('title');

        $this->assertIsArray($result);
        $fake->assertCalled('generateEmbeddings');
    }

    public function test_ai_embed_returns_empty_for_null(): void
    {
        $model = new FakeModel(['title' => null]);
        $this->assertEquals([], $model->aiEmbed('title'));
    }

    public function test_ai_analyze_sentiment(): void
    {
        AI::fake(['analyzeSentiment' => ['score' => 0.9, 'category' => 'positive']]);

        $result = $this->model->aiAnalyzeSentiment('body');

        $this->assertEquals(0.9, $result['score']);
        $this->assertEquals('positive', $result['category']);
    }

    public function test_ai_analyze_sentiment_returns_neutral_for_null(): void
    {
        $model = new FakeModel(['body' => null]);
        $result = $model->aiAnalyzeSentiment('body');
        $this->assertEquals(0, $result['score']);
        $this->assertEquals('neutral', $result['category']);
    }

    public function test_ai_classify(): void
    {
        AI::fake(['classifyText' => ['category' => 'tech', 'confidence' => 0.95]]);

        $result = $this->model->aiClassify('body', ['tech', 'sports']);

        $this->assertEquals('tech', $result['category']);
    }

    public function test_ai_classify_returns_default_for_null(): void
    {
        $model = new FakeModel(['body' => null]);
        $result = $model->aiClassify('body', ['tech', 'sports']);
        $this->assertEquals('tech', $result['category']);
        $this->assertEquals(0, $result['confidence']);
    }

    public function test_ai_extract_entities(): void
    {
        AI::fake([
            'extractEntities' => fn () => [['entity' => 'Test', 'type' => 'MISC']],
        ]);

        $result = $this->model->aiExtractEntities('body');

        $this->assertCount(1, $result);
        $this->assertEquals('Test', $result[0]['entity']);
    }

    public function test_ai_generate_image(): void
    {
        AI::fake(['generateImage' => 'https://example.com/img.png']);

        $result = $this->model->aiGenerateImage('description');

        $this->assertEquals('https://example.com/img.png', $result);
    }

    public function test_ai_generate_image_returns_empty_for_null(): void
    {
        $model = new FakeModel(['description' => null]);
        $this->assertEquals('', $model->aiGenerateImage('description'));
    }

    public function test_ai_summarize(): void
    {
        $fake = AI::fake(['generateText' => 'Short summary']);

        $result = $this->model->aiSummarize('body', 50);

        $this->assertEquals('Short summary', $result);
        $fake->assertCalledWith('generateText', fn (string $p) => str_contains($p, '50 characters'));
    }

    public function test_ai_translate(): void
    {
        $fake = AI::fake(['generateText' => 'Texto traducido']);

        $result = $this->model->aiTranslate('body', 'Spanish');

        $this->assertEquals('Texto traducido', $result);
        $fake->assertCalledWith('generateText', fn (string $p) => str_contains($p, 'Spanish'));
    }

    // --- Deprecated methods still work ---

    public function test_deprecated_complete_text_delegates_to_ai_generate_text(): void
    {
        AI::fake(['generateText' => 'response']);

        $result = $this->model->completeText('body');

        $this->assertEquals('response', $result);
    }

    public function test_deprecated_embed_attribute_delegates(): void
    {
        AI::fake(['generateEmbeddings' => [[0.1]]]);

        $result = $this->model->embedAttribute('title');

        $this->assertIsArray($result);
    }

    public function test_deprecated_analyze_sentiment_of_delegates(): void
    {
        AI::fake(['analyzeSentiment' => ['score' => 0.5, 'category' => 'positive']]);

        $result = $this->model->analyzeSentimentOf('body');

        $this->assertEquals('positive', $result['category']);
    }

    public function test_deprecated_returns_defaults_for_null(): void
    {
        $model = new FakeModel(['body' => null]);
        $this->assertEquals('', $model->completeText('body'));
        $this->assertEquals([], $model->embedAttribute('body'));
        $this->assertEquals([], $model->extractEntitiesFrom('body'));
        $this->assertEquals('', $model->generateImageFrom('body'));
        $this->assertEquals('', $model->summarizeAttribute('body'));
        $this->assertEquals('', $model->translateAttribute('body', 'es'));
    }
}
