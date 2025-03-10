# Contribuyendo a Laravel AI Bridge

¡Gracias por considerar contribuir a Laravel AI Bridge! Este documento describe el proceso para contribuir al proyecto y ayuda a hacer que el proceso de desarrollo sea fácil y efectivo para todos los involucrados.

## Código de Conducta

Al participar en este proyecto, aceptas cumplir con el [Código de Conducta](CODE_OF_CONDUCT.md). Por favor, léelo antes de contribuir.

## Primeros pasos

1. **Haz un Fork del Repositorio**
   - Realiza un fork del repositorio en GitHub a tu propia cuenta.

2. **Clona tu Fork**
   ```bash
   git clone https://github.com/edfavieljr/laravel-ai-bridge.git
   cd laravel-ai-bridge
   ```

3. **Configura el Entorno de Desarrollo**
   ```bash
   composer install
   ```

4. **Crea una Rama**
   - Crea una rama para tu funcionalidad o corrección de error:
   ```bash
   git checkout -b feature/nombre-de-tu-funcionalidad
   # o
   git checkout -b fix/nombre-de-tu-correccion
   ```

## Pautas de Desarrollo

### Estándares de Codificación

Este proyecto sigue los estándares de codificación [PSR-12](https://www.php-fig.org/psr/psr-12/). Para asegurarte de que tu código cumple con estos estándares, puedes usar PHP_CodeSniffer:

```bash
./vendor/bin/phpcs
```

Para corregir automáticamente problemas de estándares de codificación:

```bash
./vendor/bin/phpcbf
```

### Pruebas

Todas las nuevas funcionalidades o correcciones de errores deben estar cubiertas por pruebas. Este proyecto utiliza PHPUnit para pruebas:

```bash
./vendor/bin/phpunit
```

### Documentación

- Actualiza el README.md con detalles de los cambios en la interfaz, si corresponde.
- Actualiza los comentarios PHPDoc para cualquier código modificado.
- Si tus cambios requieren una nueva dependencia o un cambio en la configuración, actualiza las secciones de instalación y configuración en la documentación.

## Proceso de Pull Request

1. **Actualiza tu Fork**
   - Asegúrate de que tu fork esté actualizado con el repositorio principal:
   ```bash
   git remote add upstream https://github.com/edfavieljr/laravel-ai-bridge.git
   git fetch upstream
   git merge upstream/main
   ```

2. **Envía tus Cambios**
   ```bash
   git push origin feature/nombre-de-tu-funcionalidad
   ```

3. **Envía un Pull Request**
   - Ve a tu repositorio en GitHub y haz clic en el botón "Pull Request".
   - Proporciona una descripción detallada de los cambios y referencia cualquier issue relacionado.

4. **Revisión de Código**
   - Al menos un mantenedor revisará tu código.
   - Aborda cualquier comentario o cambio solicitado.

5. **Fusión**
   - Una vez aprobado, un mantenedor fusionará tu PR.

## Solicitudes de Funcionalidades y Reportes de Errores

Usamos los issues de GitHub para rastrear errores públicos y solicitudes de funcionalidades. Por favor, asegúrate de que tu descripción sea clara y tenga instrucciones suficientes para reproducir el problema.

## Añadiendo un Nuevo Proveedor de IA

Si estás agregando soporte para un nuevo proveedor de IA:

1. Crea una nueva clase de servicio en `src/Services/` que implemente `AIServiceInterface`.
2. Crea una facade correspondiente en `src/Facades/`.
3. Actualiza el `AIBridgeServiceProvider` para registrar tu nuevo servicio.
4. Actualiza el archivo de configuración para incluir ajustes para el nuevo proveedor.
5. Agrega cobertura de pruebas para el nuevo proveedor.
6. Actualiza la documentación para incluir ejemplos de uso del nuevo proveedor.

## Versionado

Seguimos [Versionado Semántico](https://semver.org/). Dado un número de versión MAJOR.MINOR.PATCH:

- Versión MAJOR para cambios incompatibles en la API
- Versión MINOR para adiciones de funcionalidad compatibles con versiones anteriores
- Versión PATCH para correcciones de errores compatibles con versiones anteriores

## Licencia

Al contribuir a Laravel AI Bridge, aceptas que tus contribuciones estarán bajo la [Licencia MIT](LICENSE.md) del proyecto.

## ¿Preguntas?

Si tienes alguna pregunta o necesitas más aclaraciones, no dudes en abrir un issue con la etiqueta "pregunta".

¡Gracias por contribuir a Laravel AI Bridge!