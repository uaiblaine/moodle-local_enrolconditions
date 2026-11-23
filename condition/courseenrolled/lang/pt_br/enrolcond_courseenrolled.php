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
 * Strings for component 'enrolcond_courseenrolled', language 'pt_br'.
 *
 * @package    enrolcond_courseenrolled
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['active'] = 'Apenas inscrições ativas';
$string['active_help'] = 'Se só conta uma inscrição que dá acesso ao curso neste momento.

* Marcada (o padrão): a inscrição não pode estar suspensa, o seu método de inscrição precisa estar habilitado, e ela precisa estar entre as suas datas de início e de término.
* Desmarcada: qualquer inscrição no curso conta, suspensa, ainda não iniciada ou expirada, desde que exista.

**Exemplo**: desmarcada, para um curso de continuação aberto a todos que já estiveram inscritos no curso original e não tiveram a inscrição cancelada.

Uma data de início ou de término dessas inscrições que passa não verifica ninguém de novo por si só: a mudança é percebida na próxima entrada no site ou verificação periódica, se "Verificar as condições" as incluir.';
$string['courseids'] = 'Cursos';
$string['courseids_help'] = 'Os cursos em que o usuário precisa estar inscrito para atender à condição, no máximo 50.

* Uma inscrição por qualquer método de inscrição conta, exceto uma feita por este mesmo método de inscrição.
* "Inscrições exigidas" decide se um dos cursos basta ou se todos são necessários, e "Apenas inscrições ativas", se uma inscrição suspensa ou expirada conta.
* Só os cursos que você pode ver são oferecidos e aceitos.
* Se qualquer um dos cursos for excluído, ou os cursos forem restaurados de outro site (voltam sem nenhum curso escolhido aqui), a condição não pode ser avaliada, em qualquer uma das opções de "Inscrições exigidas": ela recusa novas inscrições até que um professor escolha os cursos de novo, esteja ou não marcada como "Não deve ser atendida". Ninguém já inscrito é suspenso, removido ou reativado por causa disso.

**Exemplo**: "Álgebra I" e "Geometria I", com "Inscrições exigidas" definida como "Todos os cursos", para uma oficina de revisão aberta a quem faz os dois.

Quando "Verificar as condições" inclui "Quando algo de que as condições dependem muda", uma inscrição criada, alterada ou removida nesses cursos verifica de novo as inscrições do usuário logo depois.';
$string['descall'] = 'Você está inscrito em todos estes cursos: {$a}';
$string['descany'] = 'Você está inscrito em pelo menos um destes cursos: {$a}';
$string['descmissing'] = 'curso que não existe mais (id {$a})';
$string['descone'] = 'Você está inscrito no curso {$a}';
$string['invalidcourse'] = 'Escolha um ou mais cursos.';
$string['missingcourse'] = 'Um curso desta condição não existe mais; um professor precisa escolher os cursos de novo.';
$string['mode'] = 'Inscrições exigidas';
$string['mode_help'] = 'Em quantos dos "Cursos" escolhidos o usuário precisa estar inscrito.

* **Pelo menos um dos cursos**: uma inscrição em qualquer um deles basta.
* **Todos os cursos**: o usuário precisa estar inscrito em cada um deles.

**Quando usar cada uma**

* Várias turmas do mesmo curso de pré-requisito, e qualquer uma delas serve: pelo menos um dos cursos.
* Um projeto final aberto só a quem faz todos os módulos do programa: todos os cursos.

**Exemplo**: "Python - turma A" e "Python - turma B", com pelo menos um dos cursos, para um laboratório aberto aos alunos de qualquer uma das duas turmas.

Com um único curso, as duas opções dão no mesmo.';
$string['modeall'] = 'Todos os cursos';
$string['modeany'] = 'Pelo menos um dos cursos';
$string['noaccess'] = 'Você não pode usar um curso que não pode ver. Escolha cursos entre os oferecidos.';
$string['pluginname'] = 'Inscrito em um curso';
$string['privacy:metadata'] = 'A condição de inscrição Inscrito em um curso não guarda nenhum dado pessoal.';
$string['toomany'] = 'Escolha no máximo {$a} cursos.';
