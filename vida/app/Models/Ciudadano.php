<?php

namespace App\Models;

use App\Models\Scopes\AmbitoUoScope;
use App\Traits\Auditable;
use App\Traits\TieneDireccion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Ciudadania\Models\CiudadanoPrestacionResumen;

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
 * @property string $nombre Cifrado
 * @property string $apellido1 Cifrado
 * @property string|null $apellido2 Cifrado
 * @property string $fecha_nacimiento Cifrada
 * @property string $sexo
 * @property string|null $direccion_texto Cifrada — texto libre original
 * @property bool $direccion_normalizada
 * @property string|null $documento_identidad Cifrado
 * @property bool $es_vvg
 * @property bool $es_psh
 * @property bool $colectivo_extra_protegido
 * @property string|null $colectivo_principal
 * @property string|null $zona_intervencion
 * @property float|null $pernocta_lat
 * @property float|null $pernocta_lng
 * @property bool $activo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @todo Mover a Modules\Ciudadania\Models\Ciudadano y completar
 *       con todos los atributos, relaciones y lógica de dominio.
 */
class Ciudadano extends Model
{
    use Auditable;
    use HasFactory;
    use SoftDeletes;
    use TieneDireccion;

    /**
     * Columna PK del ciudadano usada por AmbitoUoScope.
     * El ámbito de UO se resuelve buscando Historias Sociales del ciudadano.
     */
    public string $ambitoCiudadanoPk = 'id';

    /** @var string */
    protected $table = 'ciudadanos';

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * Registra el Global Scope de ámbito de UO para filtrado automático.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new AmbitoUoScope);
    }

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
        'telefono_hash',
        'email',
        'email_hash',
        'documento_identidad',
        'nivel_identificacion',
        'contexto_alta',
        'primera_demanda',
        'activo',
        // Colectivos especiales (anonimización)
        'es_vvg',
        'es_psh',
        'colectivo_extra_protegido',
        'colectivo_principal',
        'zona_intervencion',
        'pernocta_lat',
        'pernocta_lng',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'nombre' => 'encrypted',
        'apellido1' => 'encrypted',
        'apellido2' => 'encrypted',
        'fecha_nacimiento' => 'encrypted',
        'direccion_texto' => 'encrypted',
        'telefono' => 'encrypted',
        'email' => 'encrypted',
        'primera_demanda' => 'string',
        'documento_identidad' => 'encrypted',
        'activo' => 'boolean',
        'es_vvg' => 'boolean',
        'es_psh' => 'boolean',
        'colectivo_extra_protegido' => 'boolean',
        // TieneDireccion inyecta sus propios casts via initializeTieneDireccion()
    ];

    // -------------------------------------------------------------------------
    // Accesores
    // -------------------------------------------------------------------------

    /**
     * Nombre completo del ciudadano: nombre + apellido1 [+ apellido2].
     * Los campos están cifrados — solo accesible mediante Eloquent ORM.
     */
    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido1} {$this->apellido2}");
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Resumen de prestaciones y actividades sin historia social asociada.
     * Alimentado por observers de cada módulo origen.
     *
     * @return HasMany<CiudadanoPrestacionResumen, self>
     */
    /** El ciudadano es la entidad raíz: su propio id es el ciudadano_id. */
    public function getCiudadanoId(): ?int
    {
        return $this->id;
    }

    public function prestacionesResumen(): HasMany
    {
        return $this->hasMany(CiudadanoPrestacionResumen::class);
    }
}
