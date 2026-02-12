# Documentación de Arquitectura

Bienvenido a la documentación de arquitectura. Este directorio contiene documentos detallados y enfocados sobre la arquitectura del CI4 API Starter.

---

## 📚 Mapa de Aprendizaje

### 🟢 Principiante (Día 1 - Entiende lo básico)

**Comienza aquí si eres nuevo en el proyecto:**

1. **[OVERVIEW.es.md](OVERVIEW.es.md)** (~15 min lectura)
   - ¿Qué es este proyecto?
   - Diagrama de arquitectura de alto nivel
   - Principios SOLID
   - Responsabilidades de las capas

2. **[LAYERS.es.md](LAYERS.es.md)** (~20 min lectura)
   - Profundización en Controller, Service, Model, Entity
   - Ejemplos de código para cada capa
   - Reglas y responsabilidades

3. **[REQUEST_FLOW.es.md](REQUEST_FLOW.es.md)** (~15 min lectura)
   - Ciclo completo de request/response
   - Recorrido paso a paso con ejemplo
   - Timing y rendimiento

**Inversión de tiempo:** ~50 minutos
**Sabrás:** Cómo funciona el sistema de extremo a extremo

---

### 🟡 Intermedio (Semana 1 - Domina los sistemas)

**Lee estos para entender los subsistemas clave:**

4. **[FILTERS.es.md](FILTERS.es.md)** (~10 min lectura)
   - Pipeline de middleware
   - JwtAuth, RoleAuth, Throttle, CORS
   - Creación de filtros personalizados

5. **[VALIDATION.es.md](VALIDATION.es.md)** (~15 min lectura)
   - 3 niveles de validación
   - Clases de validación de entrada
   - Reglas de validación de modelo
   - Validación de reglas de negocio

6. **[EXCEPTIONS.es.md](EXCEPTIONS.es.md)** (~10 min lectura)
   - Jerarquía de excepciones
   - Cuándo usar cada excepción
   - Manejo de excepciones en controladores

7. **[RESPONSES.es.md](RESPONSES.es.md)** (~10 min lectura)
   - Librería ApiResponse
   - Estándares de estructura de respuesta
   - Respuestas de éxito, error y paginadas

**Inversión de tiempo:** ~45 minutos
**Sabrás:** Cómo manejar entrada, validar y responder

---

### 🔴 Avanzado (Mes 1 - Conviértete en experto)

**Lee estos para características avanzadas:**

8. **[QUERIES.es.md](QUERIES.es.md)** (~20 min lectura)
   - Uso avanzado de QueryBuilder
   - Filtrado con operadores
   - Búsqueda (FULLTEXT vs LIKE)
   - Ordenamiento y paginación

9. **[SERVICES.es.md](SERVICES.es.md)** (~15 min lectura)
   - Contenedor IoC
   - Inyección de dependencias
   - Registro de servicios
   - Instancias compartidas

10. **[AUTHENTICATION.es.md](AUTHENTICATION.es.md)** (~25 min lectura)
    - Flujo de autenticación JWT
    - Access tokens vs refresh tokens
    - Revocación de tokens
    - Consideraciones de seguridad

11. **[PATTERNS.es.md](PATTERNS.es.md)** (~15 min lectura)
    - Patrón Service Layer
    - Patrón Repository
    - Patrón Factory
    - Patrón Strategy
    - Todos los patrones de diseño utilizados

12. **[I18N.es.md](I18N.es.md)** (~10 min lectura)
    - Sistema de internacionalización
    - Estructura de archivos de idioma
    - Uso de traducciones
    - Detección de locale

13. **[EXTENSION_GUIDE.es.md](EXTENSION_GUIDE.es.md)** (~20 min lectura)
    - Cómo agregar nuevos recursos
    - Cómo agregar nuevos filtros
    - Cómo agregar nuevas excepciones
    - Mejores prácticas

**Inversión de tiempo:** ~1 hora 45 minutos
**Sabrás:** Todas las características avanzadas y cómo extender el sistema

---

## 📖 Índice de Documentos

| Documento | Tema | Líneas | Audiencia |
|----------|-------|-------|----------|
| [OVERVIEW.es.md](OVERVIEW.es.md) | Visión general de arquitectura | ~200 | Principiante |
| [LAYERS.es.md](LAYERS.es.md) | Las 4 capas en detalle | ~300 | Principiante |
| [REQUEST_FLOW.es.md](REQUEST_FLOW.es.md) | Ciclo completo de request | ~250 | Principiante |
| [FILTERS.es.md](FILTERS.es.md) | Sistema de middleware | ~200 | Intermedio |
| [VALIDATION.es.md](VALIDATION.es.md) | Validación multi-nivel | ~200 | Intermedio |
| [EXCEPTIONS.es.md](EXCEPTIONS.es.md) | Manejo de excepciones | ~150 | Intermedio |
| [RESPONSES.es.md](RESPONSES.es.md) | Librería ApiResponse | ~150 | Intermedio |
| [QUERIES.es.md](QUERIES.es.md) | Consultas avanzadas | ~250 | Avanzado |
| [SERVICES.es.md](SERVICES.es.md) | Contenedor IoC | ~150 | Avanzado |
| [AUTHENTICATION.es.md](AUTHENTICATION.es.md) | Sistema de auth JWT | ~300 | Avanzado |
| [PATTERNS.es.md](PATTERNS.es.md) | Patrones de diseño | ~200 | Avanzado |
| [I18N.es.md](I18N.es.md) | Internacionalización | ~150 | Avanzado |
| [EXTENSION_GUIDE.es.md](EXTENSION_GUIDE.es.md) | Extender el sistema | ~250 | Avanzado |

**Total:** ~2,700 líneas
**Beneficio:** Documentos enfocados y digeribles

---

## 🗺️ Mapa de Documentos (Qué leer cuándo)

### Quiero...

**...entender el panorama general**
→ Lee [OVERVIEW.es.md](OVERVIEW.es.md)

**...saber cómo fluye un request**
→ Lee [REQUEST_FLOW.es.md](REQUEST_FLOW.es.md)

**...entender dónde poner código**
→ Lee [LAYERS.es.md](LAYERS.es.md)

**...agregar un nuevo recurso CRUD**
→ Lee [EXTENSION_GUIDE.es.md](EXTENSION_GUIDE.es.md)

**...entender autenticación**
→ Lee [AUTHENTICATION.es.md](AUTHENTICATION.es.md)

**...agregar filtros/middleware**
→ Lee [FILTERS.es.md](FILTERS.es.md)

**...entender validación**
→ Lee [VALIDATION.es.md](VALIDATION.es.md)

**...agregar filtrado/búsqueda avanzada**
→ Lee [QUERIES.es.md](QUERIES.es.md)

**...entender excepciones**
→ Lee [EXCEPTIONS.es.md](EXCEPTIONS.es.md)

**...formatear respuestas API**
→ Lee [RESPONSES.es.md](RESPONSES.es.md)

**...entender inyección de dependencias**
→ Lee [SERVICES.es.md](SERVICES.es.md)

**...agregar traducciones**
→ Lee [I18N.es.md](I18N.es.md)

**...ver todos los patrones de diseño**
→ Lee [PATTERNS.es.md](PATTERNS.es.md)

---

## 🎯 Referencia Rápida

Para una referencia rápida condensada, basada en tablas, optimizada para búsqueda rápida:
→ Ver [`../AGENT_QUICK_REFERENCE.md`](../AGENT_QUICK_REFERENCE.md)

Para tutorial paso a paso, práctico:
→ Ver [`../GETTING_STARTED.md`](../GETTING_STARTED.md)

---

## 💡 Consejos para Leer

1. **No leas todo de una vez** - Sigue el mapa de aprendizaje según tu nivel de experiencia

2. **Lee en orden** - Cada documento se basa en los anteriores

3. **Prueba los ejemplos** - Crea un recurso de prueba mientras lees EXTENSION_GUIDE.es.md

4. **Usa el mapa** - Salta a temas específicos según sea necesario

5. **Consulta de nuevo** - Mantén este README marcado para navegación rápida

---

## 🚀 Próximos Pasos Después de Leer

Una vez que hayas completado el mapa de aprendizaje:

1. **Construye algo** - Crea un nuevo recurso CRUD desde cero
2. **Lee el código** - Examina controladores, servicios y modelos existentes
3. **Ejecuta tests** - Ve cómo funciona el testing en todas las capas
4. **Contribuye** - Mejora la documentación o agrega características

---

**¡Feliz aprendizaje!** 📚

Si encuentras problemas o tienes sugerencias para mejorar estos documentos, por favor abre un issue o PR.
