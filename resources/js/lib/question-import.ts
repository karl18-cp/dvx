import JSZip from 'jszip';

export type ImportedQuestion = {
    question_text: string;
    question_type: string;
    points: string;
    difficulty: string;
    feedback: string;
    options: { option_text: string; is_correct: boolean }[];
    source: string;
};
const canonical = (value: string) =>
    value.toLowerCase().replace(/[^a-z0-9]/g, '');
const letters = 'ABCDEFGHIJKLMNOPQRST';

export function questionIssues(question: ImportedQuestion): string[] {
    const errors: string[] = [];

    if (!['easy', 'medium', 'hard'].includes(question.difficulty)) {
        errors.push('Choose a difficulty.');
    }

    if (!question.question_text.trim()) {
        errors.push('Question text is required.');
    }

    if (question.question_text.length > 10000) {
        errors.push('Question exceeds 10,000 characters.');
    }

    if (!(Number(question.points) > 0)) {
        errors.push('Points must be greater than zero.');
    }

    if (question.question_type !== 'short_answer') {
        if (question.options.length < 2 || question.options.length > 20) {
            errors.push('Provide 2–20 choices.');
        }

        if (question.options.some((option) => !option.option_text.trim())) {
            errors.push('Fill in every choice.');
        }

        const correct = question.options.filter(
            (option) => option.is_correct,
        ).length;

        if (
            question.question_type === 'multiple_selection'
                ? correct < 1
                : correct !== 1
        ) {
            errors.push('Select the correct answer(s).');
        }
    }

    return errors;
}

export function makeQuestion(
    text: string,
    choices: string[],
    answer: string,
    type: string,
    source: string,
): ImportedQuestion {
    const normalized = canonical(type);
    const trueFalse =
        ['truefalse', 'tf', 'boolean'].includes(normalized) ||
        (!choices.length && /^(true|false)$/i.test(answer.trim()));
    const options = (trueFalse ? ['True', 'False'] : choices).map((choice) => ({
        option_text: choice
            .replace(/\s*\[(?:correct|answer)\]\s*$/i, '')
            .replace(/^\*\s*/, '')
            .trim(),
        is_correct:
            /^\*/.test(choice) || /\[(?:correct|answer)\]\s*$/i.test(choice),
    }));
    const tokens = answer
        .split(/[,;|]/)
        .map((part) => part.trim().replace(/[).]$/, '').toLowerCase())
        .filter(Boolean);
    options.forEach((option, index) => {
        if (
            tokens.includes(letters[index].toLowerCase()) ||
            tokens.includes(String(index + 1)) ||
            tokens.includes(option.option_text.toLowerCase()) ||
            answer.trim().toLowerCase() === option.option_text.toLowerCase()
        ) {
            option.is_correct = true;
        }
    });
    const inferred = trueFalse
        ? 'true_false'
        : options.length
          ? options.filter((o) => o.is_correct).length > 1
              ? 'multiple_selection'
              : 'multiple_choice'
          : 'short_answer';
    const aliases: Record<string, string> = {
        mcq: 'multiple_choice',
        multiplechoice: 'multiple_choice',
        multipleselection: 'multiple_selection',
        selectall: 'multiple_selection',
        shortanswer: 'short_answer',
        essay: 'short_answer',
        truefalse: 'true_false',
        tf: 'true_false',
    };
    const questionType = aliases[normalized] || inferred;

    return {
        question_text: text.trim(),
        question_type: questionType,
        points: '1',
        difficulty: 'medium',
        feedback:
            questionType === 'short_answer' && answer
                ? 'Reference answer: ' + answer
                : '',
        options: questionType === 'short_answer' ? [] : options,
        source,
    };
}

/** Recognizes explicitly numbered questions, options A–T, and Answer: lines. */
export function parseQuestionLines(
    lines: string[],
    source: string,
): ImportedQuestion[] {
    const result: ImportedQuestion[] = [];
    let current: {
        text: string;
        choices: string[];
        answer: string;
        line: number;
    } | null = null;
    const finish = () => {
        if (current?.text.trim()) {
            result.push(
                makeQuestion(
                    current.text,
                    current.choices,
                    current.answer,
                    '',
                    source + ' · line ' + current.line,
                ),
            );
        }

        current = null;
    };
    lines.forEach((raw, index) => {
        const line = raw.trim();

        if (!line) {
            return;
        }

        const answer = line.match(
            /^(?:correct\s+answers?|answers?|answer\s+key)\s*[:=-]\s*(.+)$/i,
        );

        if (answer && current) {
            current.answer = answer[1];

            return;
        }

        const option = line.match(/^([A-Ta-t])[).:]\s+(.+)$/);

        if (option && current) {
            current.choices.push(option[2]);

            return;
        }

        const question = line.match(
            /^(?:question\s*|q\s*)?\d+\s*[).:-]\s*(.+)$/i,
        );

        if (question) {
            finish();
            current = {
                text: question[1],
                choices: [],
                answer: '',
                line: index + 1,
            };
        } else if (
            line.endsWith('?') &&
            (!current ||
                current.answer ||
                current.choices.length ||
                current.text.trim().endsWith('?'))
        ) {
            finish();
            current = { text: line, choices: [], answer: '', line: index + 1 };
        } else if (current && !current.answer) {
            if (current.choices.length) {
                current.choices[current.choices.length - 1] += ' ' + line;
            } else {
                current.text += '\n' + line;
            }
        }
    });
    finish();

    return result;
}

export function parseQuestionRows(
    rows: string[][],
    source: string,
): ImportedQuestion[] {
    const headerIndex = rows.findIndex((row) =>
        row.some((cell) =>
            ['question', 'questiontext', 'questions', 'questionstem'].includes(
                canonical(cell),
            ),
        ),
    );

    if (headerIndex < 0) {
        if (rows.every((row) => row.filter(Boolean).length <= 1)) {
            const lines = rows.flat().filter(Boolean);
            const listed = parseQuestionLines(lines, source);

            return listed.length
                ? listed
                : lines
                      .filter((line) => line.trim().endsWith('?'))
                      .map((line) => makeQuestion(line, [], '', '', source));
        }

        return [];
    }

    const headers = rows[headerIndex].map(canonical);
    const column = (...names: string[]) =>
        headers.findIndex((header) => names.includes(header));
    const questionColumn = column(
        'question',
        'questiontext',
        'questions',
        'questionstem',
    );
    const answerColumn = column(
        'answer',
        'correctanswer',
        'correctanswers',
        'answerkey',
    );
    const typeColumn = column('type', 'questiontype');

    return rows.slice(headerIndex + 1).flatMap((row, index) => {
        const text = row[questionColumn]?.trim();

        if (!text) {
            return [];
        }

        const choices = headers.flatMap((header, i) =>
            /^(?:option|choice)?[a-t]$/.test(header) ||
            /^(?:option|choice)\d+$/.test(header)
                ? [row[i] || '']
                : [],
        );

        while (choices.length && !choices.at(-1)?.trim()) {
            choices.pop();
        }

        const question = makeQuestion(
            text,
            choices,
            row[answerColumn] || '',
            row[typeColumn] || '',
            source + ' · row ' + (headerIndex + index + 2),
        );
        question.points = row[column('points', 'score')] || '1';
        question.difficulty =
            row[column('difficulty')]?.toLowerCase() || 'medium';
        question.feedback =
            row[column('explanation', 'feedback')] || question.feedback;

        return [question];
    });
}

const nodes = (element: Document | Element, name: string) =>
    Array.from(element.getElementsByTagNameNS('*', name));
const attr = (element: Element, name: string) =>
    element.getAttribute(name) || element.getAttribute('w:' + name) || '';
const texts = (element: Element) =>
    nodes(element, 't')
        .map((node) => node.textContent || '')
        .join('');
function xml(text: string): Document {
    if (/<!DOCTYPE|<!ENTITY/i.test(text)) {
        throw new Error('Unsupported XML declarations in this document.');
    }

    const parsed = new DOMParser().parseFromString(text, 'application/xml');

    if (parsed.getElementsByTagName('parsererror').length) {
        throw new Error('The file contains invalid document data.');
    }

    return parsed;
}
async function readXml(zip: JSZip, path: string): Promise<Document | null> {
    const entry = zip.file(path);

    if (!entry) {
        return null;
    }

    const text = await new Promise<string>((resolve, reject) => {
        let result = '';
        type TextStream = {
            on(event: 'data', callback: (chunk: string) => void): TextStream;
            on(event: 'error', callback: (error: Error) => void): TextStream;
            on(event: 'end', callback: () => void): TextStream;
            pause(): TextStream;
            resume(): TextStream;
        };
        const stream = (
            entry as typeof entry & {
                internalStream(type: 'string'): TextStream;
            }
        ).internalStream('string');
        stream
            .on('data', (chunk) => {
                result += chunk;

                if (result.length > 8_000_000) {
                    stream.pause();
                    reject(
                        new Error(
                            'The document is too large after decompression. Split it into smaller files.',
                        ),
                    );
                }
            })
            .on('error', reject)
            .on('end', () => resolve(result))
            .resume();
    });

    return xml(text);
}

export async function detectQuestions(file: File): Promise<ImportedQuestion[]> {
    if (!/\.(xlsx|docx|txt)$/i.test(file.name)) {
        throw new Error(
            'Choose an Excel .xlsx, Word .docx, or plain-text .txt file. Save older .xls/.doc files in the newer format first.',
        );
    }

    if (file.size > 5 * 1024 * 1024) {
        throw new Error('Choose a file smaller than 5 MB.');
    }

    if (/\.txt$/i.test(file.name)) {
        const content = (await file.text()).replace(/^\uFEFF/, '');
        const questions = parseQuestionLines(
            content.split(/\r\n?|\n/),
            file.name,
        );
        if (!questions.length) {
            throw new Error(
                'No questions detected. Use numbered questions, A. / B. choices, and Answer: lines in the text file.',
            );
        }
        if (questions.length > 100) {
            throw new Error(
                'More than 100 questions were found. Split the file into batches of at most 100.',
            );
        }

        return questions;
    }

    const zip = await JSZip.loadAsync(await file.arrayBuffer());

    if (Object.keys(zip.files).length > 1000) {
        throw new Error(
            'This document contains too many parts. Split it into smaller files.',
        );
    }

    const questions: ImportedQuestion[] = [];

    if (/\.xlsx$/i.test(file.name)) {
        if (!zip.file('xl/workbook.xml')) {
            throw new Error('This is not a valid Excel workbook.');
        }

        const strings = await readXml(zip, 'xl/sharedStrings.xml');
        const shared = strings ? nodes(strings, 'si').map(texts) : [];
        const sheets = Object.keys(zip.files).filter((name) =>
            /^xl\/worksheets\/sheet\d+\.xml$/.test(name),
        );

        if (sheets.length > 20) {
            throw new Error('Please upload a workbook with at most 20 sheets.');
        }

        for (const path of sheets) {
            const sheet = await readXml(zip, path);

            if (!sheet) {
                continue;
            }

            const rows = nodes(sheet, 'row').map((row) => {
                const values: string[] = [];

                for (const cell of nodes(row, 'c')) {
                    const reference = cell.getAttribute('r') || '';
                    const column =
                        reference
                            .replace(/\d/g, '')
                            .split('')
                            .reduce(
                                (total, letter) =>
                                    total * 26 + letter.charCodeAt(0) - 64,
                                0,
                            ) - 1;

                    if (column < 0 || column > 200) {
                        continue;
                    }

                    const raw = nodes(cell, 'v')[0]?.textContent || '';
                    values[column] =
                        cell.getAttribute('t') === 's'
                            ? shared[Number(raw)] || ''
                            : cell.getAttribute('t') === 'inlineStr'
                              ? texts(cell)
                              : raw;
                }

                return Array.from(
                    { length: values.length },
                    (_, i) => values[i] || '',
                );
            });
            questions.push(
                ...parseQuestionRows(rows, path.split('/').at(-1) || 'Sheet'),
            );
        }
    } else {
        const document = await readXml(zip, 'word/document.xml');

        if (!document) {
            throw new Error('This is not a valid Word document.');
        }

        for (const table of nodes(document, 'tbl')) {
            questions.push(
                ...parseQuestionRows(
                    nodes(table, 'tr').map((row) =>
                        nodes(row, 'tc').map((cell) =>
                            nodes(cell, 'p').map(texts).join('\n'),
                        ),
                    ),
                    'Word table',
                ),
            );
        }

        const numbering = await readXml(zip, 'word/numbering.xml');
        const formats = new Map<string, string>();

        if (numbering) {
            for (const num of nodes(numbering, 'num')) {
                const abstractId = nodes(num, 'abstractNumId')[0];
                const abstract = nodes(numbering, 'abstractNum').find(
                    (item) =>
                        attr(item, 'abstractNumId') ===
                        (abstractId ? attr(abstractId, 'val') : ''),
                );

                if (abstract) {
                    for (const level of nodes(abstract, 'lvl')) {
                        formats.set(
                            attr(num, 'numId') + ':' + attr(level, 'ilvl'),
                            attr(nodes(level, 'numFmt')[0] || level, 'val'),
                        );
                    }
                }
            }
        }

        const counters = new Map<string, number>();
        const lines = nodes(document, 'p')
            .filter((paragraph) => {
                let parent = paragraph.parentNode;

                while (parent) {
                    if (
                        parent.nodeType === 1 &&
                        (parent as Element).localName === 'tbl'
                    ) {
                        return false;
                    }

                    parent = parent.parentNode;
                }

                return true;
            })
            .map((paragraph) => {
                let text = texts(paragraph);
                const num = nodes(paragraph, 'numId')[0];

                if (num && !/^\d+[).]|^[A-Ta-t][).]/.test(text)) {
                    const level = nodes(paragraph, 'ilvl')[0];
                    const key =
                        attr(num, 'val') +
                        ':' +
                        (level ? attr(level, 'val') : '0');
                    const count = (counters.get(key) || 0) + 1;
                    counters.set(key, count);
                    const format = formats.get(key);

                    if (format === 'decimal') {
                        text = count + '. ' + text;
                    } else if (
                        format === 'lowerLetter' ||
                        format === 'upperLetter'
                    ) {
                        text = letters[(count - 1) % 20] + '. ' + text;
                    }
                }

                return text;
            });
        questions.push(...parseQuestionLines(lines, 'Word document'));
    }

    if (!questions.length) {
        throw new Error(
            'No questions detected. Use a Question column in Excel, or numbered questions with A. / B. choices and Answer: lines in Word. Image-only documents are not supported.',
        );
    }

    if (questions.length > 100) {
        throw new Error(
            'More than 100 questions were found. Split the file into batches of at most 100.',
        );
    }

    return questions;
}
