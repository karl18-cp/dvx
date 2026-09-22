import { Head, Link, router } from "@inertiajs/react";
import { Alert, Button, Checkbox, TextField } from "@mui/material";
import type { FormEvent} from "react";
import { useState } from "react";

type BankQuestion = {
  id: number;
  question_text: string;
  question_type: string;
  points: string;
  skill?: { name: string };
  options: { option_text: string }[];
};
type PageData = {
  data: BankQuestion[];
  prev_page_url?: string;
  next_page_url?: string;
  current_page: number;
  last_page: number;
};

export default function SimpleQuestionBank({
  assessment,
  slot,
  availableSlots,
  questions,
  search,
  type,
}: {
  assessment: { id: number; title: string };
  slot: number;
  availableSlots: number;
  questions: PageData;
  search: string;
  type: string;
}) {
  const [query, setQuery] = useState(search);
  const [selected, setSelected] = useState<number[]>([]);
  const [processing, setProcessing] = useState(false);
  const submit = (event: FormEvent) => {
    event.preventDefault();
    router.get(
      `/management/assessments/${assessment.id}/simple-question-bank`,
      { slot, search: query, type },
      { preserveState: true },
    );
  };
  const toggle = (id: number) =>
    setSelected((current) =>
      current.includes(id)
        ? current.filter((item) => item !== id)
        : current.length < availableSlots
          ? [...current, id]
          : current,
    );
  const addSelected = () => {
    setProcessing(true);
    router.post(
      `/management/assessments/${assessment.id}/simple-question-bank`,
      { bank_question_ids: selected, starting_question_id: slot || null },
      { onFinish: () => setProcessing(false) },
    );
  };
  const visitPage = (url?: string) =>
    url && router.get(url, {}, { preserveState: true, preserveScroll: true });

  return (
    <main className="min-h-full bg-[#f7f7fa] p-4 lg:p-6">
      <Head title="Choose from Question Bank" />
      <div className="mx-auto max-w-4xl space-y-5">
        <header>
          <p className="text-xs font-bold tracking-[.18em] text-[#b72822] uppercase">
            Question Bank
          </p>
          <h1 className="text-2xl font-bold">
            Select questions for {assessment.title}
          </h1>
          <p className="text-sm text-slate-600">
            Check multiple compatible questions, then add them to the available
            slots together.
          </p>
        </header>
        <Alert severity={availableSlots > 0 ? "info" : "warning"}>
          {availableSlots} question slot{availableSlots === 1 ? "" : "s"}{" "}
          available. {selected.length} selected.
        </Alert>
        <form
          className="flex gap-2 rounded-xl border bg-white p-4"
          onSubmit={submit}
        >
          <TextField
            fullWidth
            size="small"
            label="Search questions"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
          <Button type="submit" variant="contained">
            Search
          </Button>
        </form>
        <section className="space-y-3">
          {questions.data.map((question) => {
            const checked = selected.includes(question.id);
            const disabled = !checked && selected.length >= availableSlots;

            return (
              <article
                key={question.id}
                className={`rounded-xl border bg-white p-4 shadow-sm ${checked ? "border-red-700 ring-1 ring-red-700" : ""}`}
              >
                <label className="flex cursor-pointer items-start gap-3">
                  <Checkbox
                    checked={checked}
                    disabled={disabled}
                    onChange={() => toggle(question.id)}
                  />
                  <div>
                    <h2 className="font-bold">{question.question_text}</h2>
                    <p className="mt-1 text-sm text-slate-500">
                      {question.question_type.replaceAll("_", " ")} -{" "}
                      {question.skill?.name || "No skill"} - bank value{" "}
                      {question.points} points
                    </p>
                    <p className="mt-2 text-sm">
                      {question.options
                        .map((option) => option.option_text)
                        .join(" / ") || "Manual review answer"}
                    </p>
                  </div>
                </label>
              </article>
            );
          })}
          {questions.data.length === 0 && (
            <div className="rounded-xl border bg-white p-8 text-center text-slate-500">
              No compatible questions found.
            </div>
          )}
        </section>
        <div className="sticky bottom-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-white p-3 shadow-lg">
          <Link
            href={`/management/assessments/${assessment.id}/simple-builder`}
          >
            <Button>Back to Builder</Button>
          </Link>
          <div className="flex items-center gap-2">
            <Button
              disabled={!questions.prev_page_url}
              onClick={() => visitPage(questions.prev_page_url)}
            >
              Previous
            </Button>
            <span className="text-sm">
              Page {questions.current_page} of {questions.last_page}
            </span>
            <Button
              disabled={!questions.next_page_url}
              onClick={() => visitPage(questions.next_page_url)}
            >
              Next
            </Button>
          </div>
          <Button
            variant="contained"
            disabled={!selected.length || processing}
            onClick={addSelected}
          >
            {processing ? "Adding..." : `Add Selected (${selected.length})`}
          </Button>
        </div>
      </div>
    </main>
  );
}

SimpleQuestionBank.layout = {
  breadcrumbs: [
    { title: "Assessments", href: "/management/assessments" },
    { title: "Question Bank", href: "#" },
  ],
};
