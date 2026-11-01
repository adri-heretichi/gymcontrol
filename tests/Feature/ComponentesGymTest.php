<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pruebas de renderizado de los componentes Blade de la rama feature/componentes-interfaz.
 * No usan base de datos ni rutas: dibujan cada componente y revisan el HTML.
 */
class ComponentesGymTest extends TestCase
{
    // ---------- estado ----------

    public function test_estado_autorizado_se_muestra_en_verde(): void
    {
        $this->blade('<x-gym.estado estado="autorizado" />')
            ->assertSee('Autorizado')
            ->assertSee('bg-emerald-100', false);
    }

    public function test_estado_denegado_acepta_texto_personalizado(): void
    {
        $this->blade('<x-gym.estado estado="denegado" texto="Acceso denegado" />')
            ->assertSee('Acceso denegado')
            ->assertSee('bg-red-100', false);
    }

    public function test_estado_pendiente_se_muestra_en_ambar(): void
    {
        $this->blade('<x-gym.estado estado="pendiente" />')
            ->assertSee('Pendiente')
            ->assertSee('bg-amber-100', false);
    }

    public function test_estado_desconocido_se_muestra_neutral(): void
    {
        $this->blade('<x-gym.estado estado="suspendido" />')
            ->assertSee('Suspendido')
            ->assertSee('bg-white', false)
            ->assertDontSee('bg-emerald-100', false)
            ->assertDontSee('bg-red-100', false);
    }

    // ---------- tarjeta-indicador ----------

    public function test_tarjeta_muestra_titulo_valor_y_descripcion(): void
    {
        $this->blade('<x-gym.tarjeta-indicador titulo="Personas presentes" valor="12" descripcion="Dentro del gimnasio" />')
            ->assertSeeInOrder(['Personas presentes', '12', 'Dentro del gimnasio']);
    }

    public function test_tarjeta_muestra_el_cero_y_no_un_guion(): void
    {
        $this->blade('<x-gym.tarjeta-indicador titulo="Cuotas vencidas" :valor="$cantidad" />', ['cantidad' => 0])
            ->assertSee('Cuotas vencidas')
            ->assertDontSee('—');
    }

    public function test_tarjeta_sin_valor_muestra_un_guion(): void
    {
        $this->blade('<x-gym.tarjeta-indicador titulo="Grupos" :valor="$cantidad" />', ['cantidad' => null])
            ->assertSee('—');
    }

    // ---------- alerta ----------

    public function test_alerta_advertencia_es_amarilla_y_se_anuncia(): void
    {
        $this->blade('<x-gym.alerta tipo="advertencia" mensaje="Inscripción pendiente de aprobación." />')
            ->assertSee('Inscripción pendiente de aprobación.')
            ->assertSee('bg-amber-50', false)
            ->assertSee('role="alert"', false);
    }

    public function test_alerta_acepta_el_mensaje_entre_etiquetas(): void
    {
        $this->blade('<x-gym.alerta tipo="exito">Pago registrado correctamente.</x-gym.alerta>')
            ->assertSee('Pago registrado correctamente.')
            ->assertSee('role="status"', false);
    }

    public function test_alerta_de_tipo_desconocido_se_muestra_como_info(): void
    {
        $this->blade('<x-gym.alerta tipo="otro" mensaje="Hola" />')
            ->assertSee('bg-sky-50', false);
    }

    // ---------- dato ----------

    public function test_dato_muestra_etiqueta_y_valor(): void
    {
        $this->blade('<x-gym.dato etiqueta="Disciplina" valor="Funcional" />')
            ->assertSeeInOrder(['Disciplina', 'Funcional']);
    }

    public function test_dato_vacio_muestra_sin_datos(): void
    {
        $this->blade('<x-gym.dato etiqueta="Grupo" valor="" />')
            ->assertSee('Sin datos');
    }

    public function test_dato_acepta_textos_largos(): void
    {
        $texto = str_repeat('Kickboxing Muay Thai ', 20);

        $this->blade('<x-gym.dato etiqueta="Disciplina" :valor="$texto" />', ['texto' => $texto])
            ->assertSee(trim($texto));
    }

    public function test_dato_nunca_muestra_un_pin(): void
    {
        $this->blade('<x-gym.dato etiqueta="PIN" valor="4821" />')
            ->assertDontSee('4821')
            ->assertSee('Dato protegido');
    }

    // ---------- marca ----------

    public function test_marca_panel_muestra_las_dos_identidades(): void
    {
        $this->blade('<x-gym.marca />')
            ->assertSee('GymControl')
            ->assertSee('Gimnasio Fénix');
    }

    public function test_marca_terminal_muestra_gymcontrol_como_secundaria(): void
    {
        $this->blade('<x-gym.marca variante="terminal" />')
            ->assertSee('Sistema')
            ->assertSee('GymControl');
    }
}