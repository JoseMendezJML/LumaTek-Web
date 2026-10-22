<dl class="subscription-limits">
    @foreach(['max_greenhouses' => 'Invernaderos', 'max_zones_per_greenhouse' => 'Zonas por invernadero', 'max_sensors_per_zone' => 'Sensores por zona', 'max_users' => 'Usuarios', 'max_additional_admins' => 'Administradores adicionales'] as $field => $label)
        <div><dt>{{ $label }}</dt><dd>{{ $limits->{$field} }}</dd></div>
    @endforeach
</dl>
