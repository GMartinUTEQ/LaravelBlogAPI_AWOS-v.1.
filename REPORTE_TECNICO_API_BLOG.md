# REPORTE TÉCNICO - API RESTful Blog con Laravel y JWT

---

## 1. INTRODUCCIÓN

Este documento describe el proceso completo para crear una API RESTful para un sistema de blogs con autenticación JWT en Laravel 12. El sistema incluye gestión de usuarios, roles (admin/user), creación de entradas de blog con título, contenido e imagen, y sistema de comentarios.

---

## 2. REQUISITOS PREVIOS

- PHP 8.2+
- Composer instalado
- MySQL 5.7+
- Servidor Apache (XAMPP, WAMP, MAMP, o Apache nativo)
- Extensiones PHP requeridas: mbstring, xml, zip, curl, json

---

## 3. CREACIÓN DEL PROYECTO LARAVEL

### 3.1 Instalación mediante Composer

```bash
# Crear nuevo proyecto Laravel
composer create-project laravel/laravel blog-api

# Ingresar al directorio del proyecto
cd blog-api
```

### 3.2 Configuración del archivo .env

Editar el archivo `.env` con la configuración de la base de datos:

```env
# Configuración de la aplicación
APP_NAME=Laravel
APP_ENV=local
APP_KEY=base64:0FhllmJcNGbD/ZAuJnloXd871QNvV2QjZiDSWuIjgJo=
APP_DEBUG=true
APP_URL=http://localhost

# Configuración de la base de datos MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=AWOS2024        # Nombre de la base de datos
DB_USERNAME=root           # Usuario de MySQL
DB_PASSWORD=desarrollo      # Contraseña de MySQL

# Configuración de sesiones (usar archivo para evitar dependencia de tabla sessions)
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Configuración de caché
CACHE_STORE=file

# Configuración JWT (se genera automáticamente más adelante)
JWT_SECRET=1GheVLquR8twu7zMyNayRrd353c5nicYgHuK38oyUO3ChifoQgjZO7PfWzza75Ft
```

---

## 4. ESTRUCTURA DE LA BASE DE DATOS

### 4.1 Script SQL Completo

Ejecutar el siguiente script en MySQL para crear la base de datos y las tablas necesarias:

```sql
-- ============================================================
-- CREACIÓN DE LA BASE DE DATOS
-- ============================================================

-- Crear la base de datos (si no existe)
CREATE DATABASE IF NOT EXISTS AWOS2024 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE AWOS2024;

-- ============================================================
-- TABLA: users (usuarios)
-- ============================================================
-- Nota: Esta tabla ya existe en Laravel por defecto, 
-- agregamos la columna 'role' y los timestamps

-- Verificar si la tabla users existe y agregar columnas
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user' AFTER password,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL;

-- ============================================================
-- TABLA: blogs (entradas del blog)
-- ============================================================
CREATE TABLE IF NOT EXISTS blogs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    contenido TEXT NOT NULL,
    imagen VARCHAR(255) DEFAULT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: comments (comentarios)
-- ============================================================
CREATE TABLE IF NOT EXISTS comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contenido TEXT NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    blog_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 Diagrama de Relaciones

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│      users      │       │      blogs      │       │    comments     │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id (PK)        │       │ id (PK)         │       │ id (PK)         │
│ name           │       │ titulo          │       │ contenido       │
│ email          │       │ contenido       │       │ user_id (FK)    │
│ password       │◄──────│ imagen          │       │ blog_id (FK)    │
│ role           │  1:N  │ user_id (FK)    │◄──────│ created_at       │
│ created_at     │       │ created_at      │  1:N  │ updated_at       │
│ updated_at     │       │ updated_at      │       └─────────────────┘
└─────────────────┘       └─────────────────┘
                              ▲
                              │
                              └──────────────────────────┐
                                                         │
                                                         ▼
                                                   Relación:
                                                   - Un usuario puede crear muchos blogs (1:N)
                                                   - Un blog puede tener muchos comentarios (1:N)
                                                   - Un comentario pertenece a un usuario y un blog
```

---

## 5. INSTALACIÓN DEL PAQUETE JWT

### 5.1 Instalar tymon/jwt-auth

```bash
# Instalar el paquete JWT
composer require tymon/jwt-auth --no-interaction

# Publicar la configuración
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"

# Generar la clave secreta JWT
php artisan jwt:secret
```

---

## 6. ARCHIVOS CREADOS Y MODIFICADOS

### 6.1 Modelo: User.php

**Ubicación:** `app/Models/User.php`

```php
<?php

namespace App\Models;

// Importaciones necesarias
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Modelo User - Representa un usuario del sistema
 * 
 * Implementa JWTSubject para permitir autenticación mediante tokens JWT
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $role (admin o user)
 * @property timestamp $email_verified_at
 * @property timestamp $created_at
 * @property timestamp $updated_at
 */
class User extends Authenticatable implements JWTSubject
{
    // Traits que proporcionan funcionalidad adicional
    use HasFactory, Notifiable;

    /**
     * Atributos que son asignables masivamente
     * @var array
     */
    protected $fillable = [
        'name',      // Nombre del usuario
        'email',     // Correo electrónico (único)
        'password',  // Contraseña (se hashhea automáticamente)
        'role',      // Rol del usuario: 'admin' o 'user'
    ];

    /**
     * Atributos que deben ocultarse en las respuestas JSON
     * @var array
     */
    protected $hidden = [
        'password',         // Nunca mostrar la contraseña
        'remember_token',    // Token de "recordarme"
    ];

    /**
     * Conversiones de tipos de atributos
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',  // Convertir a objeto Carbon
            'password' => 'hashed',             // Hash automático de contraseña
        ];
    }

    /**
     * Obtener el identificador JWT del usuario
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        // Retorna la clave primaria del usuario para el token JWT
        return $this->getKey();
    }

    /**
     * Obtener claims personalizados para el token JWT
     * @return array
     */
    public function getJWTCustomClaims(): array
    {
        // Claims adicionales que se pueden agregar al token
        return [
            'role' => $this->role,    // Incluir el rol en el token
            'name' => $this->name,    // Incluir el nombre
        ];
    }

    /**
     * Relación: Un usuario puede tener muchos blogs
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }

    /**
     * Relación: Un usuario puede tener muchos comentarios
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

### 6.2 Modelo: Blog.php (NUEVO)

**Ubicación:** `app/Models/Blog.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Blog - Representa una entrada del blog
 * 
 * @property int $id
 * @property string $titulo
 * @property string $contenido
 * @property string|null $imagen
 * @property int $user_id
 * @property timestamp $created_at
 * @property timestamp $updated_at
 */
class Blog extends Model
{
    /**
     * Atributos que son asignables masivamente
     * @var array
     */
    protected $fillable = [
        'titulo',     // Título del blog
        'contenido',  // Contenido principal
        'imagen',     // URL de la imagen (opcional)
        'user_id',    // ID del usuario que crea el blog
    ];

    /**
     * Relación: Un blog pertenece a un usuario
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Un blog puede tener muchos comentarios
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

### 6.3 Modelo: Comment.php (NUEVO)

**Ubicación:** `app/Models/Comment.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Comment - Representa un comentario en un blog
 * 
 * @property int $id
 * @property string $contenido
 * @property int $user_id
 * @property int $blog_id
 * @property timestamp $created_at
 * @property timestamp $updated_at
 */
class Comment extends Model
{
    /**
     * Atributos que son asignables masivamente
     * @var array
     */
    protected $fillable = [
        'contenido',  // Texto del comentario
        'user_id',    // ID del usuario que comenta
        'blog_id',    // ID del blog que se comenta
    ];

    /**
     * Relación: Un comentario pertenece a un usuario
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Un comentario pertenece a un blog
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }
}
```

### 6.4 Controlador: AuthController.php

**Ubicación:** `app/Http/Controllers/AuthController.php`

```php
<?php

namespace App\Http\Controllers;

// Importaciones necesarias
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Validator;

/**
 * AuthController - Controlador de autenticación
 * 
 * Maneja las operaciones de login, registro, logout y refresh de tokens JWT
 */
class AuthController extends Controller
{
    /**
     * Constructor del controlador
     * Define las rutas que no requieren autenticación
     */
    protected $middlewareExcept = ['login', 'register'];

    /**
     * Método POST - Iniciar sesión
     * 
     * Valida las credenciales del usuario y retorna un token JWT
     * 
     * @param Request $request - Solicitud HTTP con email y password
     * @return JSON - Token de acceso o error
     */
    public function login(Request $request)
    {
        // Validar que email y password estén presentes
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',           // Email obligatorio y formato válido
            'password' => 'required|string|min:6', // Mínimo 6 caracteres
        ]);

        // Si la validación falla, retornar errores
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Obtener solo email y password
        $credentials = $request->only('email', 'password');

        // Intentar autenticar con las credenciales proporcionadas
        // NOTA: Se usa auth('api') para especificar el guard JWT
        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Retornar el token generado
        return $this->respondWithToken($token);
    }

    /**
     * Método POST - Registrar nuevo usuario
     * 
     * Crea un nuevo usuario en el sistema
     * 
     * @param Request $request - Datos del usuario (name, email, password, password_confirmation)
     * @return JSON - Usuario creado o errores de validación
     */
    public function register(Request $request)
    {
        // Validar datos del registro
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',     // Entre 2 y 100 caracteres
            'email' => 'required|email|unique:users',       // Único en la tabla users
            'password' => 'required|string|confirmed|min:6', // Confirmación obligatoria
        ]);

        // Si la validación falla, retornar errores
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        // Crear el usuario con los datos validados
        // La contraseña se hashhea antes de guardar
        $user = User::create(array_merge(
            $validator->validated(),
            ['password' => Hash::make($request->password)]
        ));

        // Retornar mensaje de éxito y usuario creado
        return response()->json([
            'message' => 'User successfully registered',
            'user' => $user,
        ], 201);
    }

    /**
     * Método GET - Obtener datos del usuario autenticado
     * 
     * Retorna la información del usuario actualmente logueado
     * 
     * @return JSON - Datos del usuario
     */
    public function me()
    {
        // auth('api')->user() obtiene el usuario del token JWT
        return response()->json(auth('api')->user());
    }

    /**
     * Método POST - Cerrar sesión
     * 
     * Invalida el token JWT actual
     * 
     * @return JSON - Mensaje de confirmación
     */
    public function logout()
    {
        // Invalidar el token actual
        auth('api')->logout();

        return response()->json(['message' => 'User successfully signed out']);
    }

    /**
     * Método POST - Refrescar token
     * 
     * Genera un nuevo token JWT antes de que expire el actual
     * 
     * @return JSON - Nuevo token de acceso
     */
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    /**
     * Método privado - Formatear respuesta con token
     * 
     * @param string $token - Token JWT generado
     * @return JSON - Respuesta con token y metadatos
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,                           // Token JWT
            'token_type' => 'bearer',                           // Tipo de token
            'expires_in' => auth('api')->factory()->getTTL() * 60, // Tiempo de expiración en segundos
        ]);
    }
}
```

### 6.5 Controlador: BlogController.php

**Ubicación:** `app/Http/Controllers/BlogController.php`

```php
<?php

namespace App\Http\Controllers;

// Importaciones necesarias
use App\Models\Blog;
use Illuminate\Http\Request;
use Validator;

/**
 * BlogController - Controlador de Blogs
 * 
 * Maneja las operaciones CRUD de los blogs
 * Solo los administradores pueden crear blogs
 */
class BlogController extends Controller
{
    /**
     * Método GET - Listar todos los blogs
     * 
     * Retorna todos los blogs con información del usuario que los creó
     * Es público (no requiere autenticación)
     * 
     * @return JSON - Array de blogs
     */
    public function index()
    {
        // Obtener todos los blogs con los datos del usuario relacionado
        // Eager loading con 'user' para optimizar consultas
        $blogs = Blog::with('user')->get();

        return response()->json($blogs);
    }

    /**
     * Método POST - Crear nuevo blog
     * 
     * Solo los usuarios con rol 'admin' pueden crear blogs
     * Requiere autenticación JWT
     * 
     * @param Request $request - Datos del blog (titulo, contenido, imagen)
     * @return JSON - Blog creado o error de autorización/validación
     */
    public function store(Request $request)
    {
        // Verificar que el usuario sea administrador
        if (auth('api')->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        // Validar datos del blog
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',  // Obligatorio, máximo 255 caracteres
            'contenido' => 'required|string',        // Obligatorio
            'imagen' => 'nullable|string',           // Opcional
        ]);

        // Si la validación falla, retornar errores
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Crear el blog asociándolo al usuario actual
        $blog = Blog::create(array_merge(
            $validator->validated(),
            ['user_id' => auth('api')->id()] // ID del usuario autenticado
        ));

        // Retornar mensaje de éxito y blog creado
        return response()->json([
            'message' => 'Blog created successfully',
            'blog' => $blog,
        ], 201);
    }

    /**
     * Método GET - Ver detalle de un blog
     * 
     * Retorna un blog específico con sus comentarios
     * Es público (no requiere autenticación)
     * 
     * @param int $id - ID del blog a buscar
     * @return JSON - Datos del blog con comentarios o error 404
     */
    public function show($id)
    {
        // Buscar blog con comentarios y usuario
        $blog = Blog::with(['user', 'comments.user'])->find($id);
        
        // Si no existe, retornar error 404
        if (! $blog) {
            return response()->json(['error' => 'Blog not found'], 404);
        }

        return response()->json($blog);
    }
}
```

### 6.6 Controlador: CommentController.php

**Ubicación:** `app/Http/Controllers/CommentController.php`

```php
<?php

namespace App\Http\Controllers;

// Importaciones necesarias
use App\Models\Blog;
use App\Models\Comment;
use Illuminate\Http\Request;
use Validator;

/**
 * CommentController - Controlador de Comentarios
 * 
 * Maneja la creación de comentarios en blogs
 * Requiere autenticación JWT
 */
class CommentController extends Controller
{
    /**
     * Método POST - Crear nuevo comentario
     * 
     * Cualquier usuario autenticado puede comentar en un blog
     * Requiere autenticación JWT
     * 
     * @param Request $request - Datos del comentario
     * @param int $blogId - ID del blog a comentar
     * @return JSON - Comentario creado o error
     */
    public function store(Request $request, $blogId)
    {
        // Verificar que el blog existe
        $blog = Blog::find($blogId);
        
        if (! $blog) {
            return response()->json(['error' => 'Blog not found'], 404);
        }

        // Validar datos del comentario
        $validator = Validator::make($request->all(), [
            'contenido' => 'required|string', // Obligatorio
        ]);

        // Si la validación falla, retornar errores
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Crear el comentario
        $comment = Comment::create([
            'contenido' => $request->contenido,           // Texto del comentario
            'user_id' => auth('api')->id(),                // ID del usuario autenticado
            'blog_id' => $blogId                           // ID del blog
        ]);

        // Retornar mensaje de éxito y comentario creado (con datos del usuario)
        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment->load('user') // Cargar datos del usuario
        ], 201);
    }
}
```

### 6.7 Controlador base: Controller.php

**Ubicación:** `app/Http/Controllers/Controller.php`

```php
<?php

namespace App\Http\Controllers;

/**
 * Controller - Clase base para todos los controladores
 * 
 * Proporciona métodos comunes para todos los controladores
 */
abstract class Controller
{
    /**
     * Método para aplicar middleware a rutas específicas
     * 
     * @param string $middleware - Nombre del middleware
     * @param array $options - Opciones adicionales
     * @return $this
     */
    public function middleware($middleware, array $options = [])
    {
        // Normalizar el middleware a array
        foreach (($middleware = is_array($middleware) ? $middleware : [$middleware]) as $m) {
            // Agregar middleware si no existe
            if (! isset($this->middleware[$m])) {
                $this->middleware[$m] = [
                    'middleware' => $m,
                    'options' => $options,
                ];
            }
        }

        return $this;
    }
}
```

### 6.8 Rutas API: api.php

**Ubicación:** `routes/api.php`

```php
<?php

// Importación de clases necesarias
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CommentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí se definen todas las rutas de la API RESTful
| 
| Rutas públicas (sin autenticación):
|   - POST /api/login
|   - POST /api/register
|   - GET /api/blogs
|   - GET /api/blogs/{id}
|
| Rutas protegidas (requieren JWT):
|   - POST /api/logout
|   - POST /api/refresh
|   - GET /api/me
|   - POST /api/blogs (solo admin)
|   - POST /api/blogs/{id}/comments
|
*/

/**
 * Ruta GET /api/user
 * Retorna el usuario autenticado actualmente
 * Requiere autenticación JWT
 */
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// ============================================================
// RUTAS PÚBLICAS (Sin autenticación)
// ============================================================

/**
 * POST /api/login
 * Iniciar sesión y obtener token JWT
 * Parámetros: email, password
 */
Route::post('/login', [AuthController::class, 'login']);

/**
 * POST /api/register
 * Registrar nuevo usuario
 * Parámetros: name, email, password, password_confirmation
 */
Route::post('/register', [AuthController::class, 'register']);

/**
 * GET /api/blogs
 * Listar todos los blogs (público)
 */
Route::get('/blogs', [BlogController::class, 'index']);

/**
 * GET /api/blogs/{id}
 * Ver detalle de un blog (público)
 */
Route::get('/blogs/{id}', [BlogController::class, 'show']);

// ============================================================
// RUTAS PROTEGIDAS (Requieren JWT)
// ============================================================

/**
 * Grupo de rutas que requieren autenticación JWT
 */
Route::group(['middleware' => 'auth:api'], function () {
    // ----------------------------------------
    // Rutas de autenticación
    // ----------------------------------------
    
    /**
     * POST /api/logout
     * Cerrar sesión (invalidar token)
     */
    Route::post('/logout', [AuthController::class, 'logout']);
    
    /**
     * POST /api/refresh
     * Refrescar token JWT
     */
    Route::post('/refresh', [AuthController::class, 'refresh']);
    
    /**
     * GET /api/me
     * Obtener datos del usuario actual
     */
    Route::get('/me', [AuthController::class, 'me']);

    // ----------------------------------------
    // Rutas de blogs
    // ----------------------------------------
    
    /**
     * POST /api/blogs
     * Crear nuevo blog (solo admins)
     * Parámetros: titulo, contenido, imagen (opcional)
     */
    Route::post('/blogs', [BlogController::class, 'store']);

    // ----------------------------------------
    // Rutas de comentarios
    // ----------------------------------------
    
    /**
     * POST /api/blogs/{id}/comments
     * Crear comentario en un blog
     * Parámetros: contenido
     */
    Route::post('/blogs/{id}/comments', [CommentController::class, 'store']);
});
```

### 6.9 Configuración de Autenticación: auth.php

**Ubicación:** `config/auth.php`

```php
<?php

// Importar el modelo User
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Configuración por defecto de autenticación
    |
    */
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Definición de los guards de autenticación
    | 
    | - web: Usa sesiones (para autenticación tradicional)
    | - api: Usa JWT (para API RESTful)
    |
    */
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // GUARD API - Usa JWT para autenticación stateless
        'api' => [
            'driver' => 'jwt',      // Driver JWT del paquete tymon/jwt-auth
            'provider' => 'users',   // Proveedor de usuarios
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Define cómo se recuperan los usuarios de la base de datos
    |
    */
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | Configuración para recuperación de contraseñas
    |
    */
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Tiempo en segundos antes de requerir confirmación de contraseña
    |
    */
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
```

### 6.10 Configuración del Bootstrap: bootstrap/app.php

**Ubicación:** `bootstrap/app.php`

```php
<?php

// Importaciones necesarias
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Crear y configurar la aplicación
return Application::configure(basePath: dirname(__DIR__))
    // Configurar las rutas del proyecto
    ->withRouting(
        web: __DIR__.'/../routes/web.php',    // Rutas web tradicionales
        api: __DIR__.'/../routes/api.php',    // Rutas API RESTful
        commands: __DIR__.'/../routes/console.php', // Comandos Artisan
        health: '/up',                          // Endpoint de health check
    )
    // Configurar middleware global
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    // Configuración de manejo de excepciones
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

---

## 7. DATOS DE PRUEBA

### 7.1 Usuarios de Prueba

Los siguientes usuarios fueron insertados en la base de datos:

| ID | Nombre   | Email               | Password   | Rol    |
|----|----------|---------------------|------------|--------|
| 14 | Admin    | admin@test.com     | password   | admin  |
| 15 | Usuario  | user@test.com      | password   | user   |

**Para crear usuarios desde Laravel Tinker:**

```bash
# Crear usuario administrador
php artisan tinker --execute="App\Models\User::create(['name'=>'Admin','email'=>'admin@test.com','password'=>bcrypt('password'),'role'=>'admin']);"

# Crear usuario normal
php artisan tinker --execute="App\Models\User::create(['name'=>'Usuario','email'=>'user@test.com','password'=>bcrypt('password'),'role'=>'user']);"
```

### 7.2 Datos de Blog de Prueba

Un blog de ejemplo fue creado:

| ID | Título        | Contenido           | Imagen      | Usuario |
|----|---------------|---------------------|-------------|---------|
| 1  | Mi primer blog| Contenido del blog | imagen.jpg | Admin   |

---

## 8. ENDPOINTS DE LA API

### 8.1 Autenticación

| Método | Endpoint                    | Descripción                        | Auth |
|--------|-----------------------------|------------------------------------|------|
| POST   | `/api/login`               | Iniciar sesión                    | No   |
| POST   | `/api/register`            | Registrar nuevo usuario           | No   |
| POST   | `/api/logout`             | Cerrar sesión                     | Sí   |
| POST   | `/api/refresh`            | Refrescar token JWT               | Sí   |
| GET    | `/api/me`                 | Obtener datos del usuario actual | Sí   |

### 8.2 Blogs

| Método | Endpoint            | Descripción                | Auth        |
|--------|---------------------|----------------------------|-------------|
| GET    | `/api/blogs`       | Listar todos los blogs    | No          |
| GET    | `/api/blogs/{id}`  | Ver detalle de blog       | No          |
| POST   | `/api/blogs`       | Crear nuevo blog          | Sí (Admin)  |

### 8.3 Comentarios

| Método | Endpoint                     | Descripción              | Auth |
|--------|-------------------------------|--------------------------|------|
| POST   | `/api/blogs/{id}/comments`  | Crear comentario        | Sí   |

---

## 9. EJEMPLOS DE USO (cURL)

### 9.1 Iniciar Sesión (Obtener Token)

```bash
curl -X POST http://localhost/blog-api/public/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@test.com","password":"password"}'
```

**Respuesta:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

### 9.2 Registrar Nuevo Usuario

```bash
curl -X POST http://localhost/blog-api/public/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Nuevo Usuario",
    "email": "nuevo@test.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### 9.3 Listar Blogs (Público)

```bash
curl http://localhost/blog-api/public/api/blogs
```

### 9.4 Ver Detalle de Blog (Público)

```bash
curl http://localhost/blog-api/public/api/blogs/1
```

### 9.5 Crear Blog (Requiere Admin)

```bash
# Reemplazar TU_TOKEN con el token obtenido en el login
curl -X POST http://localhost/blog-api/public/api/blogs \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "Mi Nuevo Blog",
    "contenido": "Este es el contenido del blog",
    "imagen": "https://ejemplo.com/imagen.jpg"
  }'
```

### 9.6 Comentar en un Blog (Usuario Logueado)

```bash
# Reemplazar TU_TOKEN con el token obtenido en el login
curl -X POST http://localhost/blog-api/public/api/blogs/1/comments \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"contenido": "Excelente artículo!"}'
```

### 9.7 Obtener Datos del Usuario Actual

```bash
# Reemplazar TU_TOKEN con el token obtenido en el login
curl -X GET http://localhost/blog-api/public/api/me \
  -H "Authorization: Bearer TU_TOKEN"
```

### 9.8 Cerrar Sesión

```bash
# Reemplazar TU_TOKEN con el token obtenido en el login
curl -X POST http://localhost/blog-api/public/api/logout \
  -H "Authorization: Bearer TU_TOKEN"
```

---

## 10. CÓDIGO RESUMEN DE ARCHIVOS IMPORTANTES

### 10.1 Resumen de cambios en .env

```env
# Cambios principales:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=AWOS2024
DB_USERNAME=root
DB_PASSWORD=desarrollo

SESSION_DRIVER=file
CACHE_STORE=file

JWT_SECRET=1GheVLquR8twu7zMyNayRrd353c5nicYgHuK38oyUO3ChifoQgjZO7PfWzza75Ft
```

### 10.2 Resumen de migraciones creadas

1. **2026_03_14_132751_add_role_to_users_table.php** - Agrega columna 'role' a users
2. **2026_03_14_133748_create_blogs_table.php** - Crea tabla blogs
3. **2026_03_14_133748_create_comments_table.php** - Crea tabla comments

---

## 11. NOTAS ADICIONALES

### 11.1 Consideraciones de Seguridad

- Los tokens JWT expiran después de 60 minutos (configurable en `config/jwt.php`)
- Las contraseñas se almacenan hasheadas usando bcrypt
- Solo los administradores pueden crear blogs
- Cualquier usuario autenticado puede comentar

### 11.2 Manejo de Errores

- 400: Error de validación
- 401: No autorizado (credenciales inválidas)
- 403: Prohibido (no tiene permisos)
- 404: Recurso no encontrado

### 11.3 Configuración Adicional (Opcional)

Para cambiar el tiempo de expiración del token JWT, editar `config/jwt.php`:

```php
'ttl' => env('JWT_TTL', 60), // Minutos hasta expiración
```

---

## 12. CREDITOS Y RECURSOS

- **Framework:** Laravel 12.x
- **Paquete JWT:** tymon/jwt-auth
- **Documentación:** https://jwt-auth.readthedocs.io/
- **Laravel Docs:** https://laravel.com/docs/12.x

---

**Documento generado el:** 14 de Marzo de 2026
**Versión:** 1.0
