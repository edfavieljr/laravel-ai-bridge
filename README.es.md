# Laravel AI Bridge

Una biblioteca potente y elegante para integrar múltiples proveedores de IA en aplicaciones Laravel a través de una API unificada.

## Introducción

Laravel AI Bridge proporciona una integración perfecta con los principales proveedores de IA (OpenAI, Anthropic Claude, Google Gemini y Hugging Face) a través de una interfaz consistente al estilo Laravel. La biblioteca abstrae las complejidades de trabajar con diferentes APIs de IA, permitiendo a los desarrolladores centrarse en construir funcionalidades en lugar de gestionar implementaciones de API.

## Características

- **API unificada** para múltiples proveedores de IA
- **Facades específicas por proveedor** para acceso directo a características especializadas
- **Caché inteligente** para reducir costos de API
- **Fallback automático** entre proveedores para mayor fiabilidad
- **Sintaxis estilo Laravel** con facades, helpers e interfaces fluidas
- **Integración con Eloquent** mediante traits para modelos con capacidades de IA
- **Registro completo** y manejo de errores

## Instalación

### Requisitos

- PHP 8.1 o superior
- Laravel 9.0 o superior
- Composer

### Vía Composer

```bash
composer require edfavieljr/laravel-ai-bridge
```

### Publicar Configuración

Después de instalar el paquete, publica el archivo de configuración:

```bash
php artisan vendor:publish --provider="edfavieljr\LaravelAIBridge\AIBridgeServiceProvider" --tag="ai-config"
```

## Configuración Rápida

La forma más rápida de comenzar es usando el comando de configuración incluido:

```bash
php artisan ai:setup
```

Este comando interactivo te guiará a través de:
1. Seleccionar tu proveedor de IA preferido
2. Configurar tus claves API
3. Establecer modelos predeterminados
4. Actualizar automáticamente tu archivo `.env`

## Configuración Manual

### Variables de Entorno

Añade las siguientes variables a tu archivo `.env`:

```
# Proveedor predeterminado
AI_PROVIDER=openai

# Configuración de OpenAI
OPENAI_API_KEY=tu-clave-openai
OPENAI_ORGANIZATION=tu-id-de-organizacion  # Opcional
OPENAI_DEFAULT_MODEL=gpt-4

# Configuración de Anthropic
ANTHROPIC_API_KEY=tu-clave-anthropic
ANTHROPIC_DEFAULT_MODEL=claude-3-opus-20240229

# Configuración de Google Gemini
GEMINI_API_KEY=tu-clave-gemini
GEMINI_PROJECT_ID=tu-id-de-proyecto-gcp  # Opcional, para Vertex AI
GEMINI_DEFAULT_MODEL=gemini-1.5-pro

# Configuración de Hugging Face
HUGGINGFACE_API_KEY=tu-clave-huggingface
HUGGINGFACE_DEFAULT_MODEL=gpt2
```

### Opciones de Configuración

El archivo `config/ai.php` contiene ajustes detallados para:

- Proveedor predeterminado
- Comportamiento de caché
- Opciones de fallback entre proveedores
- Limitación de tasas
- Registro de actividad
- Almacenamiento en base de datos para llamadas API
- Configuración específica por proveedor

## Uso Básico

### Usando la Facade Principal

```php
use edfavieljr\LaravelAIBridge\Facades\AI;

// Generar texto con el proveedor predeterminado
$respuesta = AI::generateText('Explica la computación cuántica en términos simples');

// Analizar sentimiento
$sentimiento = AI::analyzeSentiment('¡Me encanta absolutamente este producto!');

// Generar embeddings para búsqueda semántica
$embeddings = AI::generateEmbeddings('Texto para convertir en representación vectorial');

// Clasificar texto en categorías
$clasificacion = AI::classifyText(
    'La batería se agota demasiado rápido en este teléfono',
    ['problema_hardware', 'problema_software', 'problema_bateria', 'experiencia_usuario']
);

// Extraer entidades
$entidades = AI::extractEntities('Apple anunció su nuevo iPhone ayer en California');
```

### Usando Funciones Helper Globales

```php
// Generar texto
$explicacion = ai('Explica cómo funciona blockchain en términos simples');

// Analizar sentimiento
$sentimiento = ai_sentiment('El servicio al cliente fue terrible y quiero un reembolso');

// Generar embeddings
$embeddings = ai_embed('Representación vectorial para búsqueda semántica');

// Clasificar texto
$categoria = ai_classify(
    'La pantalla se congela después de la actualización',
    ['problema_hardware', 'bug_software', 'problema_compatibilidad', 'error_usuario']
);

// Extraer entidades
$entidades = ai_entities('El CEO de Microsoft Satya Nadella anunció una nueva asociación con OpenAI');
```

## Trabajar con Proveedores Específicos

### OpenAI

```php
use edfavieljr\LaravelAIBridge\Facades\OpenAI;

// Acceso directo vía facade específica del proveedor
$texto = OpenAI::generateText('Escribe un poema sobre el otoño');

// Generar una imagen
$urlImagen = OpenAI::generateImage('Una ciudad futurista con coches voladores');

// Usando la facade principal con especificación de proveedor
$texto = AI::provider('openai')
    ->model('gpt-4')
    ->generateText('Explica la teoría de la relatividad');
```

### Anthropic (Claude)

```php
use edfavieljr\LaravelAIBridge\Facades\Anthropic;

// Generar texto con Claude
$texto = Anthropic::generateText('Escribe un resumen del último informe climático');

// Usando la facade principal con especificación de proveedor
$texto = AI::provider('anthropic')
    ->model('claude-3-opus-20240229')
    ->generateText('Compara y contrasta la computación cuántica y la computación clásica');
```

### Google Gemini

```php
use edfavieljr\LaravelAIBridge\Facades\Gemini;

// Generar texto con Gemini
$texto = Gemini::generateText('Crea un tutorial para principiantes en machine learning');

// Usando la facade principal con especificación de proveedor
$texto = AI::provider('gemini')
    ->model('gemini-1.5-pro')
    ->generateText('Explica cómo funcionan las redes neuronales');
```

### Hugging Face

```php
use edfavieljr\LaravelAIBridge\Facades\HuggingFace;

// Generar texto con modelos de HuggingFace
$texto = HuggingFace::model('gpt2')->generateText('Continúa esta historia: Había una vez');

// Generar embeddings con un modelo específico
$embeddings = HuggingFace::model('sentence-transformers/all-mpnet-base-v2')
    ->generateEmbeddings('Vector para búsqueda semántica');
```

## Integración con Modelos Eloquent

Añade capacidades de IA directamente a tus modelos:

```php
use Illuminate\Database\Eloquent\Model;
use edfavieljr\LaravelAIBridge\Traits\HasAICapabilities;

class Producto extends Model
{
    use HasAICapabilities;
    
    // Tu implementación del modelo...
}
```

Luego usa las capacidades de IA en las instancias de tu modelo:

```php
$producto = Producto::find(1);

// Generar una descripción de marketing
$textoMarketing = $producto->completeText(
    'descripcion',
    'Reescribe esta descripción de producto para que sea más atractiva: %s'
);

// Analizar el sentimiento de una reseña de cliente
$sentimiento = $producto->analyzeSentimentOf('resena_cliente');

// Categorizar el producto basado en su descripción
$categoria = $producto->classifyAttribute(
    'descripcion',
    ['electronica', 'ropa', 'hogar', 'deportes']
);

// Generar una imagen para el producto
$urlImagen = $producto->generateImageFrom('descripcion');

// Resumir la descripción del producto
$resumen = $producto->summarizeAttribute('descripcion', 100);

// Traducir la descripción del producto
$traducido = $producto->translateAttribute('descripcion', 'inglés');
```

## Características Avanzadas

### Fallback Automático Entre Proveedores

Configura el comportamiento de fallback en `config/ai.php`:

```php
'fallback' => [
    'enabled' => true,
    'providers' => ['openai', 'anthropic', 'gemini', 'huggingface'],
],
```

Con el fallback habilitado, si el proveedor principal falla, la biblioteca intenta automáticamente con el siguiente proveedor:

```php
// Intentará primero con OpenAI, luego con otros proveedores si falla
$resultado = AI::provider('openai')->generateText('Explica la física cuántica');
```

### Caché Inteligente

Configura el caché en `config/ai.php`:

```php
'cache' => [
    'enabled' => true,
    'ttl' => 60, // minutos
],
```

Las solicitudes idénticas se almacenarán en caché para reducir los costos de API:

```php
// La primera llamada consulta la API
$resultado1 = AI::generateText('¿Qué es el machine learning?');

// La segunda llamada idéntica usa el resultado almacenado en caché
$resultado2 = AI::generateText('¿Qué es el machine learning?');
```

### Registro en Base de Datos

Habilita el almacenamiento en base de datos para rastrear el uso de IA:

```php
'storage' => [
    'enabled' => true,
    'purge_after_days' => 30,
],
```

Luego publica y ejecuta la migración:

```bash
php artisan vendor:publish --provider="edfavieljr\LaravelAIBridge\AIBridgeServiceProvider" --tag="ai-migrations"
php artisan migrate
```

Consulta los registros:

```php
use edfavieljr\LaravelAIBridge\Models\AICompletion;

// Obtener todas las completaciones
$completaciones = AICompletion::all();

// Obtener completaciones de un proveedor específico
$completacionesOpenAI = AICompletion::fromProvider('openai')->get();

// Obtener resumen de uso de tokens
$resumenUso = AICompletion::getTokenUsageSummary();
```

## Solución de Problemas

### Problemas Comunes

1. **Fallos de Autenticación de Clave API**
   - Verifica que tus claves API estén correctamente configuradas en el archivo `.env`
   - Revisa si hay espacios en blanco o caracteres especiales en tus claves

2. **Limitación de Tasa**
   - Configura los ajustes de limitación de tasa en `config/ai.php`
   - Implementa procesamiento basado en colas para aplicaciones de alto volumen

3. **Disponibilidad de Modelos**
   - Asegúrate de tener acceso a los modelos seleccionados en tus cuentas de proveedor
   - Algunos modelos requieren permisos o suscripciones específicas

### Depuración

Habilita el registro detallado:

```php
'logging' => [
    'enabled' => true,
    'channel' => 'ai-logs', // Crea este canal en tu config/logging.php
],
```

## Contribuir

¡Las contribuciones son bienvenidas! Por favor, consulta nuestra [Guía de Contribución](CONTRIBUTING.md) para más detalles.

## Licencia

Este paquete es software de código abierto bajo la [licencia MIT](LICENSE.md).