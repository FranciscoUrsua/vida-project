## Sexo
Todas las entidades que se refieren a personas llevarán el campo *sexo*, con opciones "M", "F" y "D" (Desconocido)

## Georeferenciación
Todas las entidades que puedan ubicarse en un mapa tendrán campos de dirección postal: Tipo de calle, nombre de calle, número, otra información, código postal, ciudad (por defecto será Madrid) y país (por defecto será España). Además tendrá campos lat y lng con las coordenadas y un campo "dirección validada" booleano que refleja si la dirección postal ha sido validada.

## Versionado
Todas las entidades que no sean auxiliares mantendrán un versionado de la información que guardan, de manera que los cambios que se producen en ellas sean trazables. UN caso típico es el documento de identidad de un SOcialUser, que puede ser primero un pasaporte, después un NIE y más tarde un DNI. Para poder identificar correctamente todas las interacciones de este SocialUser con los servicios sociales, debemos ser capaces de tratar estos cambios. Otro caso es el del cambio de nombre de un Centro. 

## Auditoría
Todas las operaciones que afecten a datos de los SocialUsers deben ser registradas. Esto incluye creación, lectura, edición y borrado. El sistema debe guardar registro de qué AppUser ha hecho la operación, sobre qué datos se ha hecho y a qué fecha+hora. 
La aplicación contará con opciones en el frontend para mostrar un subconjunto de registros relevantes.

## Accesos restringidos
Hay personas que tienen especial protección. Actualmente son mujeres víctimas de violencia de género y menores, pero en el futuro esto podría cambiar. El acceso a datos de estas personas requiere un permiso especial y será en modo solo lectura. Si el AppUser no tiene entre sus permisos el acceso a estos datos, el sistema le pedirá una motivación que justifique el acceso, y trasladará la solicitud a un AppUser con permisos para otorgar este acceso. Tanto la solicitud como la autorización o denegación quedarán registrados en el sistema de auditoría.
