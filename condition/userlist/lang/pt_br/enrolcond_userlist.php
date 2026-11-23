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
 * Strings for component 'enrolcond_userlist', language 'pt_br'.
 *
 * @package    enrolcond_userlist
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['desc'] = 'Seu nome de usuário está numa lista de {$a} usuários';
$string['emptylist'] = 'A lista de nomes de usuário está vazia; um professor precisa digitá-la de novo.';
$string['invalidusernames'] = 'Estes não são nomes de usuário válidos: {$a}';
$string['pluginname'] = 'Lista de nomes de usuário';
$string['privacy:metadata:enrol'] = 'A instância de inscrição guarda as suas condições na tabela enrol, como dados de configuração do método de inscrição.';
$string['privacy:metadata:enrol:usernames'] = 'Nomes de usuário digitados por um professor numa condição Lista de nomes de usuário. São dados de configuração da instância de inscrição: ficam guardados até que a condição ou a instância seja removida, e não são exportados nem apagados para cada usuário listado.';
$string['toomany'] = 'Uma lista pode ter no máximo {$a} nomes de usuário. Use um arquivo de nomes de usuário para listas mais longas.';
$string['usernames'] = 'Nomes de usuário';
$string['usernames_help'] = 'Os nomes de usuário dos usuários que atendem à condição, digitados aqui.

* Um nome de usuário por linha, ou separados por espaços, vírgulas ou ponto e vírgula, até 5000. Maiúsculas e minúsculas são iguais.
* Um nome de usuário que ainda não tem conta é aceito: esse usuário pode se inscrever quando a conta existir.
* A lista fica guardada no curso, com as condições deste método de inscrição, e vai junto nos backups do curso que incluem os métodos de inscrição. Uma restauração em outro site volta sem ela: a condição então não pode ser avaliada, e recusa novas inscrições, até que um professor digite a lista de novo. Ninguém já inscrito é suspenso, removido ou reativado por causa disso.

**Exemplo**: "ana.souza, bruno.lima, carla.reis", para uma turma piloto com três usuários convidados.

Para listas mais longas, ou uma lista exportada de outro sistema, a condição "Arquivo de nomes de usuário" é mais fácil de manter atualizada.';
