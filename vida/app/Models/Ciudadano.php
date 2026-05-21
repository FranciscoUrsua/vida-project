<?php

namespace App\Models;

use App\Traits\TieneDireccion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo stub de Ciudadano.
 *
 * Stub mínimo para que los módulos que referencian ciudadanos puedan
 * compilar y funcionar. La implementación completa corresponde al módulo
 * Ciudadania (docs/modulo-ciudadania.md).
 *
 * Todos los campos de datos personales se cifran en la capa de aplicación
 * con el cast 'encrypted' (AES-256). Lo que se persiste en BD es texto
 * cifrado opaco.
 *
 * Referencia definitiva: Modules\Ciudadania\Models\Ciudadano
 *
 * @property int $id
 * @property string|null $alias
 * @property string $nombre          Cifrado
 * @property string $apellido1       Cifrado
 * @property string|null $apellido2  Cifrado
 * @property string $fecha_nacimiento Cifrada
 * @property string $sexo
 * @property string|null $direccion_texto  Cifrada — texto libre original
 * @property bool        $direccion_normalizada
 * @property bool $activo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @todo Mover a Modules\Ciudadania\Models\Ciudadano y completar
 *       con todos los atributos, relaciones y lógica de dominio.
 */
class Ciudadano extends Model
{
    use HasFactory;
    use SoftDeletes;
    use TieneDireccion;

    /** @var string */
    protected $table = 'ciudadanos';

    /** @var list<string> */
    protected $fillable = [
        'alias',
        'nombre',
        'apellido1',
        'apellido2',
        'fecha_nacimiento',
        'sexo',
        // Dirección canónica — campos del trait TieneDireccion
        'direccion_texto',
        'direccion_normalizada',
        'origen_direccion',
        'tipo_via',
        'nombre_via',
        'tipo_numeracion',
        'numero',
        'portal',
        'escalera',
        'piso',
        'puerta',
        'codigo_postal',
        'municipio',
        'coordenadas_lat',
        'coordenadas_lng',
        'geocoder_proveedor',
        // Contacto
        'telefono',
        'email',
        'nivel_identificacion',
        'contexto_alta',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'nombre'            => 'encrypted',
        'apellido1'         => 'encrypted',
        'apellido2'         => 'encrypted',
        'fecha_nacimiento'  => 'encrypted',
        'direccion_texto'   => 'encrypted',
        'telefono'          => 'encrypted',
        'email'             => 'encrypted',
        'activo'            => 'boolean',
        // TieneDireccion inyecta sus propios casts via initializeTieneDireccion()
    ];
}
