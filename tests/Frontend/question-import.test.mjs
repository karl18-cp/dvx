import assert from 'node:assert/strict';
import { test } from 'node:test';
import JSZip from 'jszip';
import { DOMParser } from '@xmldom/xmldom';
import { detectQuestions } from '../../resources/js/lib/question-import.ts';
import {
    makeQuestion,
    parseQuestionLines,
    parseQuestionRows,
    questionIssues,
} from '../../resources/js/lib/question-import.ts';

test('Excel columns detect choices and multiple answers', () => {
    const rows = parseQuestionRows(
        [
            ['Question', 'Option A', 'Option B', 'Correct Answer'],
            ['Pick one', 'First', 'Second', 'B'],
            ['Pick both', 'First', 'Second', 'A,B'],
        ],
        'Sheet',
    );
    assert.equal(rows.length, 2);
    assert.equal(rows[0].options[1].is_correct, true);
    assert.equal(rows[1].question_type, 'multiple_selection');
    assert.deepEqual(questionIssues(rows[1]), []);
});
test('Word numbered lists detect questions and keys', () => {
    const rows = parseQuestionLines(
        [
            'Question 1: Greeting?',
            'A. Hello',
            'B. Goodbye',
            'Answer: A',
            '2) Explain empathy.',
        ],
        'Word',
    );
    assert.equal(rows.length, 2);
    assert.equal(rows[0].options[0].is_correct, true);
    assert.equal(rows[1].question_type, 'short_answer');
});
test('missing keys are not guessed', () => {
    const question = makeQuestion('Choose one', ['Yes', 'No'], '', '', 'Sheet');
    assert.equal(
        question.options.some((o) => o.is_correct),
        false,
    );
    assert.ok(
        questionIssues(question).includes('Select the correct answer(s).'),
    );
});
test('true false is detected from an explicit key', () => {
    const question = makeQuestion('Is this correct?', [], 'False', '', 'Word');
    assert.equal(question.question_type, 'true_false');
    assert.equal(question.options[1].is_correct, true);
});
test('empty middle options cannot shift answer letters', () => {
    const question = parseQuestionRows(
        [
            ['Question', 'A', 'B', 'C', 'Answer'],
            ['Pick C', 'First', '', 'Third', 'C'],
        ],
        'Sheet',
    )[0];
    assert.equal(question.options[2].is_correct, true);
    assert.ok(questionIssues(question).includes('Fill in every choice.'));
});
test('question-only sheets produce short answers', () => {
    const question = parseQuestionRows(
        [
            ['Question', 'Points'],
            ['Explain empathy.', '0'],
        ],
        'Sheet',
    )[0];
    assert.equal(question.question_type, 'short_answer');
    assert.ok(
        questionIssues(question).includes('Points must be greater than zero.'),
    );
});

globalThis.DOMParser = DOMParser;
test('TXT uploads support UTF-8 BOM and Windows line endings', async () => {
    const file = new File(
        [
            '\uFEFF1. Choose one.\r\nA. First\r\nB. Second\r\nAnswer: B\r\n2. Explain empathy.',
        ],
        'questions.txt',
    );
    const questions = await detectQuestions(file);
    assert.equal(questions.length, 2);
    assert.equal(questions[0].options[1].is_correct, true);
    assert.equal(questions[1].question_type, 'short_answer');
});
test('separate unnumbered text questions are not merged', async () => {
    const questions = await detectQuestions(
        new File(
            ['What is empathy?\nWhat is active listening?'],
            'questions.txt',
        ),
    );
    assert.equal(questions.length, 2);
});
test('TXT empty detection and batch limits are enforced', async () => {
    await assert.rejects(
        () => detectQuestions(new File(['Training notes'], 'notes.txt')),
        /No questions detected/,
    );
    const content = Array.from(
        { length: 101 },
        (_, i) => `${i + 1}. Explain item ${i + 1}.`,
    ).join('\n');
    await assert.rejects(
        () => detectQuestions(new File([content], 'questions.txt')),
        /at most 100/,
    );
});
test('reads an actual XLSX archive with inline-string cells', async () => {
    const zip = new JSZip();
    zip.file('xl/workbook.xml', '<workbook/>');
    const cell = (ref, text) =>
        `<c r="${ref}" t="inlineStr"><is><t>${text}</t></is></c>`;
    zip.file(
        'xl/worksheets/sheet1.xml',
        `<worksheet><sheetData><row>${cell('A1', 'Question')}${cell('B1', 'Option A')}${cell('C1', 'Option B')}${cell('D1', 'Answer')}</row><row>${cell('A2', 'Choose a greeting')}${cell('B2', 'Hello')}${cell('C2', 'Goodbye')}${cell('D2', 'A')}</row></sheetData></worksheet>`,
    );
    const file = new File(
        [await zip.generateAsync({ type: 'uint8array' })],
        'questions.xlsx',
    );
    const questions = await detectQuestions(file);
    assert.equal(questions.length, 1);
    assert.equal(questions[0].options[0].is_correct, true);
});

test('reads actual DOCX paragraphs and excludes table paragraphs from duplicate detection', async () => {
    const zip = new JSZip();
    const p = (text) => `<w:p><w:r><w:t>${text}</w:t></w:r></w:p>`;
    zip.file(
        'word/document.xml',
        `<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>${p('1. Greeting?')}${p('A. Hello')}${p('B. Goodbye')}${p('Answer: A')}<w:tbl><w:tr><w:tc>${p('Question')}</w:tc></w:tr><w:tr><w:tc>${p('2. Explain empathy.')}</w:tc></w:tr></w:tbl></w:body></w:document>`,
    );
    const file = new File(
        [await zip.generateAsync({ type: 'uint8array' })],
        'questions.docx',
    );
    const questions = await detectQuestions(file);
    assert.equal(questions.length, 2);
    assert.equal(
        questions.find((q) => q.question_text === 'Greeting?').options[0]
            .is_correct,
        true,
    );
});

test('rejects disguised archives without the required document entry', async () => {
    const zip = new JSZip();
    zip.file('hello.txt', 'not a Word document');
    const bytes = await zip.generateAsync({ type: 'uint8array' });
    await assert.rejects(
        () => detectQuestions(new File([bytes], 'wrong.docx')),
        /valid Word document/,
    );
});
