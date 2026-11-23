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
 * Strings for component 'enrolcond_date', language 'pt_br'.
 *
 * @package    enrolcond_date
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['descfrom'] = 'A partir de {$a}';
$string['descfromto'] = 'De {$a->from} até {$a->to}';
$string['descto'] = 'Até {$a}';
$string['from'] = 'A partir de';
$string['from_help'] = 'O momento a partir do qual a condição é atendida.

* Antes dele, ninguém atende à condição; a partir dele, todos atendem, até o "Até", quando ele estiver definido.
* Se este campo ficar desabilitado, a condição não tem início, e o "Até" precisa ser definido.
* Marcada como "Não deve ser atendida", ela funciona ao contrário: a condição só é atendida antes deste momento.

**Exemplo**: 1º de fevereiro de 2027, 08:00, para abrir a inscrição no primeiro dia da semana de inscrições.

Para abrir a inscrição para todos de uma vez, a "Data de início" do próprio método é mais simples, e também esconde o link "Inscreva-me" até lá. Uma condição "Data" é útil dentro de um grupo, por exemplo para deixar uma coorte entrar uma semana antes das outras.';
$string['pluginname'] = 'Data';
$string['privacy:metadata'] = 'A condição de inscrição Data não guarda nenhum dado pessoal.';
$string['required'] = 'Defina uma data de início, uma data de término ou ambas.';
$string['to'] = 'Até';
$string['to_help'] = 'O momento a partir do qual a condição deixa de ser atendida.

* A condição é atendida até este momento, e não a partir dele.
* Se este campo ficar desabilitado, a condição não tem fim, e o "A partir de" precisa ser definido; quando os dois estão definidos, o "A partir de" precisa vir antes.
* Os usuários inscritos antes dele mantêm a inscrição, a menos que "Verificar as condições" os verifique de novo quando entram no site ou periodicamente: então a primeira dessas verificações depois deste momento aplica "Quando as condições deixarem de ser atendidas". Uma data que passa não é uma mudança à qual a verificação por mudanças reage.

**Exemplo**: 30 de junho de 2027, 23:59, com a verificação periódica e "Suspender a inscrição", para um curso financiado até o fim de junho: a inscrição se encerra nesse momento, e os inscritos são suspensos nas horas seguintes.';
$string['toafterfrom'] = 'A data de término deve ser posterior à data de início.';
