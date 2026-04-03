# Contribuir a Laravel AI Bridge

Gracias por considerar contribuir a Laravel AI Bridge. Este documento describe el proceso y los estándares que seguimos.

## Código de Conducta

Al participar en este proyecto, aceptas cumplir con el [Código de Conducta](CODE_OF_CONDUCT.md). Por favor, léelo antes de contribuir.

## Primeros Pasos

1. **Haz fork del repositorio** en GitHub

2. **Clona tu fork**
   ```bash
   git clone https://github.com/TU-USUARIO/laravel-ai-bridge.git
   cd laravel-ai-bridge
   ```

3. **Instala dependencias**
   ```bash
   composer install
   ```

4. **Crea una rama**
   ```bash
   git checkout -b feature/nombre-de-tu-feature
   # o
   git checkout -b fix/nombre-del-fix
   ```

5. **Verifica que todo funciona**
   ```bash
   ./vendor/bin/phpunit
   ./vendor/bin/phpstan analyse
   ```

## Estándares de Desarrollo

### Estilo de Código

- `declare(strict_types=1)` en cada archivo PHP — sin excepciones
- Return types en todos los métodos públicos
- Usar el enum `Provider` (`src/Enums/Provider.php`) en vez de strings mágicos
- Usar el trait `HasAIInstrumentation` para lógica compartida de servicios (caché, logging, storage, manejo de errores)
- Las clases que no están diseñadas para ser extendidas deben marcarse como `final`

### Testing

Cada feature o fix debe incluir tests. Usamos PHPUnit con Orchestra Testbench:

```bash
./vendor/bin/phpunit
```

Para testear llamadas a servicios de IA, usa `Http::fake()` para tests directos y `AI::fake()` para tests de integración:

```php
// Test directo del servicio
Http::fake([
    'https://api.openai.com/*' => Http::response([...], 200),
]);
$service = new OpenAIService('key', null, ['default_model' => 'gpt-4o']);
$result = $service->generateText('test');

// Test de integración con el sistema Fake
AI::fake(['generateText' => 'respuesta mockeada']);
// ... ejecuta tu código de aplicación ...
AI::assertCalled('generateText');
```

### Análisis Estático

Usamos PHPStan nivel 5:

```bash
./vendor/bin/phpstan analyse
```

Todas las contribuciones deben pasar con cero errores.

### Documentación

- Actualiza README.md (y README.es.md) si cambias la API pública
- Actualiza CHANGELOG.md siguiendo el formato [Keep a Changelog](https://keepachangelog.com/)
- Agrega bloques PHPDoc a métodos públicos

## Agregar un Nuevo Proveedor de IA

Este es el tipo de contribución más común. Sigue estos pasos:

1. **Crea la clase del servicio** en `src/Services/TuProviderService.php`
   - Implementa `AIServiceInterface`
   - Usa el trait `HasAIInstrumentation` (te da caché, logging, eventos y storage gratis)
   - Implementa `providerName(): Provider` retornando el valor del enum
   - Maneja las particularidades del API del proveedor en tus métodos
   - Lanza `AIException::missingCapability()` para features que el proveedor no soporta

2. **Agrega un case al enum `Provider`** en `src/Enums/Provider.php`

3. **Registra el servicio** en `AIBridgeServiceProvider::register()`

4. **Agrega el proveedor al `ProviderFactory`** en `src/Services/ProviderFactory.php`

5. **Agrega configuración** en `config/ai.php` bajo la key `providers`

6. **Escribe tests** cubriendo como mínimo:
   - Generación de texto (éxito + error)
   - Todas las capacidades soportadas
   - Excepciones `missingCapability` para features no soportadas
   - Verificación de headers/autenticación
   - Cambio de modelo

7. **Actualiza la documentación**: README, README.es.md, CHANGELOG.md, y la tabla de capacidades

### Ejemplo: Servicio mínimo de un proveedor

```php
<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Services;

use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Services\Concerns\HasAIInstrumentation;

class TuProviderService implements AIServiceInterface
{
    use HasAIInstrumentation;

    protected function providerName(): Provider
    {
        return Provider::TuProvider;
    }

    // ... implementa los métodos de la interfaz
}
```

## Proceso de Pull Request

1. **Asegúrate de que todos los tests pasen** y PHPStan reporte cero errores
2. **Actualiza tu fork** con la última rama `main`
3. **Envía un PR** con una descripción clara de qué cambió y por qué
4. **Un reviewer** revisará tu código — atiende cualquier feedback
5. Una vez aprobado, un maintainer hará merge

## Reportar Bugs

Usa GitHub Issues. Incluye:
- Versión de PHP y Laravel
- Pasos para reproducir
- Comportamiento esperado vs actual
- Mensajes de error o stack traces

## Versionado

Seguimos [Versionado Semántico](https://semver.org/):

- **MAJOR** — cambios incompatibles en la API
- **MINOR** — nuevas features compatibles con versiones anteriores
- **PATCH** — fixes compatibles con versiones anteriores

## Licencia

Al contribuir, aceptas que tus contribuciones estarán bajo la [Licencia MIT](LICENSE.md) del proyecto.
