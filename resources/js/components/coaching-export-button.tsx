import {
    Alert,
    Autocomplete,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    TextField,
} from '@mui/material';
import JSZip from 'jszip';
import { useState } from 'react';

type Choice = { id: number; employee: { name: string }; coaching_date: string };
type ExportData = {
    filename: string;
    lines: string[];
    attachments: { name: string; url: string; size: number }[];
    warnings: string[];
};
const xml = (text: string) =>
    text
        .replace(
            /[<>&"']/g,
            (c) =>
                ({
                    '<': '&lt;',
                    '>': '&gt;',
                    '&': '&amp;',
                    '"': '&quot;',
                    "'": '&apos;',
                })[c]!,
        )
        // XML 1.0 forbids these control characters in DOCX text nodes.
        // eslint-disable-next-line no-control-regex
        .replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f]/g, '');

export default function CoachingExportButton({
    records,
    recordId,
}: {
    records?: Choice[];
    recordId?: number;
}) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<Choice | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const download = async () => {
        const id = recordId ?? selected?.id;

        if (!id) {
            return;
        }

        setBusy(true);
        setError('');
        setNotice('Preparing coaching log…');

        try {
            const response = await fetch(`/management/coaching/${id}/export`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(
                    'Unable to export this log. Refresh the page and check your access.',
                );
            }

            const data: ExportData = await response.json();

            if (
                data.attachments.reduce((sum, file) => sum + file.size, 0) >
                512 * 1024 * 1024
            ) {
                throw new Error(
                    'Attachments exceed the 512 MB browser export limit. Download the originals from the linked evaluation.',
                );
            }

            const zip = new JSZip();
            const doc = new JSZip();
            doc.file(
                '[Content_Types].xml',
                '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            );
            doc.file(
                '_rels/.rels',
                '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            );
            const lines = [
                ...data.lines,
                '',
                'Attachment notes',
                ...data.warnings,
            ].flatMap((line) => line.split(/\r?\n/));
            doc.file(
                'word/document.xml',
                `<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>${lines.map((line) => `<w:p><w:r><w:t xml:space="preserve">${xml(line)}</w:t></w:r></w:p>`).join('')}<w:sectPr/></w:body></w:document>`,
            );
            zip.file(
                'coaching-log.docx',
                await doc.generateAsync({ type: 'uint8array' }),
            );
            zip.file('coaching-log.txt', lines.join('\r\n'));

            for (const attachment of data.attachments) {
                setNotice(`Including ${attachment.name}…`);
                const file = await fetch(attachment.url, {
                    headers: { Accept: 'application/octet-stream' },
                });

                if (
                    !file.ok ||
                    file.redirected ||
                    file.headers.get('content-type')?.includes('text/html')
                ) {
                    throw new Error(
                        `Could not download ${attachment.name}. No incomplete ZIP was downloaded. Please try again.`,
                    );
                }

                zip.file(attachment.name, await file.blob());
            }

            const blob = await zip.generateAsync({
                type: 'blob',
                compression: 'STORE',
            });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = data.filename;
            link.click();
            window.setTimeout(() => URL.revokeObjectURL(url), 60000);
            setNotice(
                data.warnings.length
                    ? `Export downloaded. ${data.warnings.join(' ')}`
                    : 'Export downloaded with the coaching DOCX, recording and original evaluation DOCX.',
            );
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : 'Export failed. Please try again.',
            );
            setNotice('');
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Button variant="outlined" onClick={() => setOpen(true)}>
                Export Coaching Log
            </Button>
            <Dialog
                open={open}
                onClose={() => {
                    if (!busy) {
                        setOpen(false);
                    }
                }}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle>Export Coaching Log</DialogTitle>
                <DialogContent className="space-y-4">
                    <p>
                        Download a ZIP containing the coaching log as DOCX and
                        TXT, plus the linked recording and original evaluation
                        DOCX when available.
                    </p>
                    {!recordId && (
                        <Autocomplete
                            options={records ?? []}
                            value={selected}
                            onChange={(_, value) => setSelected(value)}
                            disabled={busy}
                            getOptionLabel={(row) =>
                                `#${row.id} · ${row.employee.name} · ${row.coaching_date.slice(0, 10)}`
                            }
                            renderInput={(params) => (
                                <TextField
                                    {...params}
                                    label="Choose a coaching log"
                                    helperText="Choose from the current page. Use the log filters or change pages to find another record."
                                />
                            )}
                        />
                    )}
                    {notice && <Alert severity="info">{notice}</Alert>}
                    {error && <Alert severity="error">{error}</Alert>}
                </DialogContent>
                <DialogActions>
                    <Button disabled={busy} onClick={() => setOpen(false)}>
                        Close
                    </Button>
                    <Button
                        variant="contained"
                        disabled={busy || (!recordId && !selected)}
                        onClick={() => void download()}
                    >
                        {busy ? 'Preparing…' : 'Download ZIP'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}
