# Laravel AI Bridge

Una API unificada y testeable para integrar múltiples proveedores de IA en aplicaciones Laravel.

Soporta **OpenAI**, **Anthropic Claude**, **Google Gemini** y **HuggingFace** — con streaming, conversaciones, salida estructurada, fallback automático, caché y observabilidad integrada.

## ¿Por qué este paquete?

- **Una API, cuatro proveedores** — cambia de proveedor con una línea, no una reescritura
- **Laravel 10, 11 y 12** — el SDK oficial de Laravel AI requiere 12+. Este no.
- **`AI::fake()` integrado** — testea tu código con IA sin mockear HTTP
- **Observabilidad por eventos** — cada llamada de IA dispara eventos para logging, métricas o alertas
- **Logging en base de datos** — rastrea tokens, costos y uso de manera automática

## Instalación

```bash
composer require edfavieljr/laravel-ai-bridge
```

Publica la configuración:

```bash
php artisan vendor:publish --tag=ai-config
```

Agrega tu API key al `.env`:

```env
AI_PROVIDER=openai
OPENAI_API_KEY=tu-key-aqui
```

Listo.

## Inicio Rápido

```php
use Edfavieljr\LaravelAIBridge\Facades\AI;

// Generar texto
$response = AI::generateText('Explica la computación cuántica en términos simples');

// Cambiar proveedor al vuelo
$response = AI::provider('anthropic')->generateText('Escribe un haiku sobre código');

// Cambiar modelo
$response = AI::provider('openai')->model('gpt-4o-mini')->generateText('Resume esto');
```

## Streaming

Streaming de tokens en tiempo real con generadores PHP. Funciona con OpenAI, Anthropic y Gemini.

```php
foreach (AI::streamText('Escribe una historia sobre un robot') as $chunk) {
    echo $chunk;
    ob_flush();
}
```

## Conversaciones

Chat multi-turno con manejo automático de historial.

```php
$chat = AI::conversation()
    ->system('Eres un tutor de matemáticas.');

$chat->send('¿Qué es el cálculo?');
$chat->reply('Dame un ejemplo');

$chat->messages(); // Historial completo disponible
```

## Salida Estructurada

Fuerza respuestas JSON con validación automática de schema.

```php
$result = AI::structured('Extrae info de: Juan Pérez, 30 años, vive en CDMX', [
    'nombre' => 'string',
    'edad'   => 'integer',
    'ciudad' => 'string',
])->generate();

// ['nombre' => 'Juan Pérez', 'edad' => 30, 'ciudad' => 'CDMX']
```

## Testing con `AI::fake()`

Sin mockear HTTP. Sin API keys en tests.

```php
AI::fake([
    'generateText' => '¡Hola mundo!',
    'analyzeSentiment' => ['score' => 0.9, 'category' => 'positive'],
]);

// Assertions
AI::assertCalled('generateText');
AI::assertCalledWith('generateText', fn ($prompt) => str_contains($prompt, 'hola'));
AI::assertNotCalled('generateImage');
AI::assertNothingCalled();
```

## Integración con Eloquent

```php
use Edfavieljr\LaravelAIBridge\Traits\HasAICapabilities;

class Articulo extends Model
{
    use HasAICapabilities;

    protected function aiConfig(): array
    {
        return [
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-20250514',
        ];
    }
}
```

```php
$articulo = Articulo::find(1);

$articulo->aiSummarize('cuerpo', 200);
$articulo->aiTranslate('titulo', 'English');
$articulo->aiAnalyzeSentiment('contenido');
$articulo->aiClassify('cuerpo', ['tech', 'ciencia', 'política']);
```

## Eventos

Cada llamada de IA dispara eventos.

```php
use Edfavieljr\LaravelAIBridge\Events\AIRequestCompleted;
use Edfavieljr\LaravelAIBridge\Events\AIRequestFailed;

Event::listen(AIRequestCompleted::class, function ($event) {
    // $event->provider, $event->model, $event->prompt, $event->completion, $event->metadata
});

Event::listen(AIRequestFailed::class, function ($event) {
    // $event->error — alerta a tu equipo, trackea tasas de fallo, etc.
});
```

## Capacidades por Proveedor

| Feature | OpenAI | Anthropic | Gemini | HuggingFace |
|---------|--------|-----------|--------|-------------|
| Generación de texto | Sí | Sí | Sí | Sí |
| Streaming | Sí | Sí | Sí | Fallback |
| Embeddings | Sí | No | Sí | Sí |
| Generación de imágenes | Sí | No | No | Sí |
| Análisis de sentimiento | Sí | Sí | Sí | Sí |
| Clasificación | Sí | Sí | Sí | Sí |
| Extracción de entidades | Sí | Sí | Sí | Sí |

## Requisitos

- PHP 8.1+
- Laravel 10, 11 o 12

## Contribuir

Ver [CONTRIBUTING.es.md](CONTRIBUTING.es.md).

## Licencia

MIT. Ver [LICENSE.md](LICENSE.md).
