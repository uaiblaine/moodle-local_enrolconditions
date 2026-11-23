<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Strings for component 'enrolcond_profile', language 'pt_br'.
 *
 * @package    enrolcond_profile
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['desc_contains'] = 'Seu campo {$a->field} contém "{$a->value}"';
$string['desc_doesnotcontain'] = 'Seu campo {$a->field} não contém "{$a->value}"';
$string['desc_endswith'] = 'Seu campo {$a->field} termina com "{$a->value}"';
$string['desc_isempty'] = 'Seu campo {$a->field} está vazio';
$string['desc_isequalto'] = 'Seu campo {$a->field} é "{$a->value}"';
$string['desc_isnotempty'] = 'Seu campo {$a->field} não está vazio';
$string['desc_startswith'] = 'Seu campo {$a->field} começa com "{$a->value}"';
$string['field'] = 'Campo do perfil do usuário';
$string['field_help'] = 'O campo do perfil do usuário que a condição compara com o "Valor", um campo padrão ou personalizado.

* Um campo que o usuário pode editar no próprio perfil é autodeclarado: o usuário pode alterá-lo para atender à condição. Quando a condição precisa ser confiável, use um campo que só os administradores ou um método de autenticação preenchem, como "Número de identificação" ou um campo personalizado bloqueado.
* O resultado da condição mostra ao professor se o campo de um participante corresponde ao valor, mesmo para um campo que o professor não pode ver no perfil.
* A comparação ignora maiúsculas e minúsculas e os espaços em volta do valor.
* Se um campo personalizado for excluído, a condição não pode ser avaliada: ela recusa novas inscrições até que um professor escolha outro campo, esteja ou não marcada como "Não deve ser atendida". Ninguém já inscrito é suspenso, removido ou reativado por causa disso.

**Exemplo**: "Departamento", "é igual a", "Enfermagem", com o departamento preenchido pelo cadastro de pessoal e bloqueado, deixa entrar os usuários do departamento de Enfermagem.

Quando "Verificar as condições" inclui "Quando algo de que as condições dependem muda", uma atualização do perfil do usuário verifica de novo as suas inscrições logo depois.';
$string['invalidfield'] = 'Escolha um campo do perfil.';
$string['invalidoperator'] = 'Escolha uma comparação.';
$string['missingfield'] = 'O campo de perfil desta condição não existe mais; um professor precisa escolher outro.';
$string['op_contains'] = 'contém';
$string['op_doesnotcontain'] = 'não contém';
$string['op_endswith'] = 'termina com';
$string['op_isempty'] = 'está vazio';
$string['op_isequalto'] = 'é igual a';
$string['op_isnotempty'] = 'não está vazio';
$string['op_startswith'] = 'começa com';
$string['operator'] = 'Comparação';
$string['pluginname'] = 'Perfil do usuário';
$string['privacy:metadata'] = 'A condição de inscrição Perfil do usuário não guarda nenhum dado pessoal.';
$string['value'] = 'Valor';
