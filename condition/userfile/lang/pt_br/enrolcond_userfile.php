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
 * Strings for component 'enrolcond_userfile', language 'pt_br'.
 *
 * @package    enrolcond_userfile
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cachedef_usernames'] = 'Nomes de usuário lidos dos arquivos de nomes de usuário';
$string['cleanupfilestask'] = 'Excluir os arquivos de nomes de usuário que nenhuma condição usa mais';
$string['desc'] = 'Seu nome de usuário está no arquivo {$a->filename} ({$a->count} usuários)';
$string['empty'] = 'O arquivo não tem nomes de usuário.';
$string['file'] = 'Arquivo de nomes de usuário';
$string['file_help'] = 'Um arquivo com os nomes de usuário dos usuários que atendem à condição.

* Um arquivo CSV ou de texto com um nome de usuário por linha, na primeira coluna, até 200000 nomes de usuário. Uma primeira linha "username" é lida como cabeçalho. Maiúsculas e minúsculas são iguais.
* O arquivo fica guardado no curso, com a condição: para mudar a lista, envie um arquivo novo aqui. Editar o original no seu computador não muda nada.
* Ele vai junto nos backups do curso que incluem os métodos de inscrição. Uma restauração em outro site volta sem ele: a condição então não pode ser avaliada, e recusa novas inscrições, até que um professor envie o arquivo de novo.
* Uma condição cujo arquivo está ausente nunca suspende, remove ou reativa ninguém já inscrito.

**Exemplo**: "enfermagem-2027.csv", exportado do sistema de admissão, com os nomes de usuário dos alunos admitidos neste ano.

Um nome de usuário no arquivo que ainda não tem conta é aceito: esse usuário pode se inscrever quando a conta existir. Para poucos usuários, a condição "Lista de nomes de usuário" é mais rápida.';
$string['filemissing'] = 'O arquivo de nomes de usuário desta condição não existe mais.';
$string['invalidusernames'] = 'Estes não são nomes de usuário válidos: {$a}';
$string['pluginname'] = 'Arquivo de nomes de usuário';
$string['privacy:metadata:core_files'] = 'Arquivos de nomes de usuário enviados pelos professores, que listam os usuários que atendem à condição.';
$string['toomany'] = 'Um arquivo pode ter no máximo {$a} nomes de usuário.';
