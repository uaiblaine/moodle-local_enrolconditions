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
 * Strings for component 'enrolcond_coursecompleted', language 'pt_br'.
 *
 * @package    enrolcond_coursecompleted
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['course'] = 'Curso';
$string['course_help'] = 'O curso que o usuário precisa ter concluído para atender à condição.

* A condição é atendida assim que esse curso registra a conclusão do curso pelo usuário. A conclusão de curso precisa estar habilitada, com critérios, nesse curso; caso contrário, ninguém nunca a atende.
* Só os cursos que você pode ver são oferecidos e aceitos.
* Marcada como "Não deve ser atendida", ela deixa entrar quem ainda não concluiu o curso.
* Se o curso for excluído, ou restaurado de outro site (volta sem nenhum curso escolhido aqui), a condição não pode ser avaliada: ela recusa novas inscrições até que um professor escolha o curso de novo, esteja ou não marcada como "Não deve ser atendida". Ninguém já inscrito é suspenso, removido ou reativado por causa disso.

**Exemplo**: "Segurança básica", para que só quem concluiu o curso de segurança possa se inscrever no avançado.

Quando "Verificar as condições" inclui "Quando algo de que as condições dependem muda", concluir esse curso verifica de novo as inscrições do usuário logo depois.';
$string['desc'] = 'Você concluiu o curso {$a}';
$string['descmissing'] = 'Você concluiu um curso que não existe mais (id {$a})';
$string['invalidcourse'] = 'Escolha um curso.';
$string['missingcourse'] = 'O curso desta condição não existe mais; um professor precisa escolher outro.';
$string['noaccess'] = 'Você não pode usar um curso que não pode ver. Escolha um dos cursos oferecidos.';
$string['pluginname'] = 'Curso concluído';
$string['privacy:metadata'] = 'A condição de inscrição Curso concluído não guarda nenhum dado pessoal.';
