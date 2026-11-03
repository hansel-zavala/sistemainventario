<x-mail::message>
# Tu contraseña fue actualizada

La contraseña de tu cuenta en el Sistema de Inventario y Servicios Técnicos se cambió {{ $fromProfile ? 'desde Mi perfil' : 'mediante el proceso de recuperación' }}.

**Fecha y hora:** {{ $changedAt }}

@if ($fromProfile)
Los demás accesos quedaron invalidados. La sesión donde realizaste el cambio permanece abierta hasta su vencimiento habitual.
@else
Los accesos anteriores quedaron invalidados. Para volver a ingresar, utiliza tu nueva contraseña.
@endif

Si realizaste este cambio, no necesitas hacer nada más.

**Si no reconoces este cambio, contacta de inmediato al Departamento de Informática de la Municipalidad de La Ceiba.**

Nunca compartas tu contraseña ni tus códigos de recuperación.

Municipalidad de La Ceiba · Departamento de Informática
</x-mail::message>
