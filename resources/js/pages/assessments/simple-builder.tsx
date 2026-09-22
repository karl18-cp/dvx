import { Head, Link, router, useForm } from "@inertiajs/react";
import {
  Alert,
  Button,
  Checkbox,
  LinearProgress,
  MenuItem,
  Radio,
  TextField,
} from "@mui/material";
import { useEffect, useMemo, useState } from "react";

type Option = { id?: number; option_text: string; is_correct: boolean };
type Question = {
  id: number;
  question_text: string;
  question_type: string;
  points: string;
  skill_id?: number;
  skill?: { name: string };
  feedback?: string;
  options: Option[];
  ready: boolean;
};
type Assessment = {
  id: number;
  title: string;
  description?: string;
  status: string;
  passing_score: string;
  time_limit_minutes?: number;
  maximum_attempts: number;
  planned_question_count: number;
  planned_total_points: number;
  applies_to_all_campaigns: boolean;
  campaigns: { name: string; abbreviation: string }[];
  questions: Question[];
  random_pools: unknown[];
  training_materials: { title: string; is_required: boolean }[];
  training_attachments: { is_required: boolean; material: { title: string } }[];
};

const initialOptions = (): Option[] =>
  Array.from({ length: 4 }, (_, index) => ({
    option_text: "",
    is_correct: index === 0,
  }));

export default function SimpleBuilder({
  assessment,
  skills,
  reviewMode,
  readinessErrors,
}: {
  assessment: Assessment;
  skills: { id: number; name: string }[];
  reviewMode: boolean;
  readinessErrors: string[];
}) {
  const [current, setCurrent] = useState(() =>
    typeof window === "undefined"
      ? 0
      : Number(
          sessionStorage.getItem(`assessment-${assessment.id}-question`) || 0,
        ),
  );
  const selected = assessment.questions[current];
  const ready = assessment.questions.filter(
    (question) => question.ready,
  ).length;
  const form = useForm({
    question_text: "",
    question_type: "multiple_choice",
    points: "1",
    skill_id: "",
    feedback: "",
    is_required: true,
    options: initialOptions(),
  });
  useEffect(() => {
    if (!selected) {
return;
}

    const values = {
      question_text: selected.question_text,
      question_type: selected.question_type,
      points: selected.points,
      skill_id: selected.skill_id ? String(selected.skill_id) : "",
      feedback: selected.feedback || "",
      is_required: true,
      options:
        selected.question_type === "short_answer"
          ? []
          : selected.options.length
            ? selected.options.map(({ option_text, is_correct }) => ({
                option_text,
                is_correct,
              }))
            : initialOptions(),
    };
    form.setData(values);
    form.clearErrors();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selected?.id]);
  const campaign = assessment.applies_to_all_campaigns
    ? "All Campaigns"
    : assessment.campaigns
        .map((item) => item.abbreviation || item.name)
        .join(", ");
  const training = [
    ...assessment.training_materials.map((item) => item.title),
    ...assessment.training_attachments.map((item) => item.material.title),
  ];
  const total = useMemo(
    () =>
      assessment.questions.reduce(
        (sum, question) => sum + Number(question.points),
        0,
      ),
    [assessment.questions],
  );
  const changeType = (type: string) =>
    form.setData({
      ...form.data,
      question_type: type,
      options:
        type === "true_false"
          ? [
              { option_text: "True", is_correct: true },
              { option_text: "False", is_correct: false },
            ]
          : type === "short_answer"
            ? []
            : form.data.options.length >= 2
              ? form.data.options
              : initialOptions(),
    });
  const setCorrect = (index: number, checked: boolean) =>
    form.setData(
      "options",
      form.data.options.map((option, optionIndex) => ({
        ...option,
        is_correct:
          form.data.question_type === "multiple_selection"
            ? optionIndex === index
              ? checked
              : option.is_correct
            : optionIndex === index,
      })),
    );
  const hasChanges = Boolean(
    selected &&
      (form.data.question_text !== selected.question_text ||
        form.data.question_type !== selected.question_type ||
        Number(form.data.points) !== Number(selected.points) ||
        form.data.skill_id !==
          (selected.skill_id ? String(selected.skill_id) : "") ||
        form.data.feedback !== (selected.feedback || "") ||
        JSON.stringify(form.data.options) !==
          JSON.stringify(
            selected.question_type === "short_answer"
              ? []
              : selected.options.length
                ? selected.options.map(({ option_text, is_correct }) => ({
                    option_text,
                    is_correct,
                  }))
                : initialOptions(),
          )),
  );
  const save = (next = false, destination?: number) =>
    form.put(
      `/management/assessments/${assessment.id}/questions/${selected.id}`,
      {
        preserveScroll: true,
        onSuccess: () => {
          if (destination !== undefined) {
setCurrent(destination);
} else if (next && current < assessment.questions.length - 1) {
setCurrent(current + 1);
}
        },
      },
    );
  const navigate = (destination: number) => {
    if (destination === current) {
return;
}

    if (hasChanges) {
save(false, destination);
} else {
setCurrent(destination);
}
  };
  const openReview = () => {
    const url = `/management/assessments/${assessment.id}/review`;

    if (hasChanges) {
      form.put(
        `/management/assessments/${assessment.id}/questions/${selected.id}`,
        { onSuccess: () => router.visit(url) },
      );
    } else {
      router.visit(url);
    }
  };

  if (reviewMode) {
return (
      <Review
        assessment={assessment}
        readinessErrors={readinessErrors}
        campaign={campaign}
        training={training}
        total={total}
      />
    );
}

  return (
    <main className="min-h-full bg-[#f7f7fa] p-4 lg:p-6">
      <Head title={`Build ${assessment.title}`} />
      <div className="mx-auto max-w-5xl space-y-5">
        <header className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <p className="text-xs font-bold tracking-[.18em] text-[#b72822] uppercase">
              Simple Question Builder
            </p>
            <h1 className="mt-1 text-2xl font-bold">{assessment.title}</h1>
            <p className="text-sm text-slate-600">
              {campaign} · {assessment.planned_total_points} points
            </p>
          </div>
          <div className="flex gap-2">
            <Link href={`/management/assessments/${assessment.id}/builder`}>
              <Button variant="outlined">Advanced Builder</Button>
            </Link>
            <Button variant="contained" onClick={openReview}>
              Review Assessment
            </Button>
          </div>
        </header>
        {assessment.random_pools?.length > 0 && (
          <Alert severity="info">
            Advanced configuration enabled. Use Advanced Builder to manage
            random pools.
          </Alert>
        )}
        <section className="rounded-2xl border bg-white p-4 shadow-sm">
          <div className="flex justify-between text-sm">
            <b>
              Questions Ready: {ready} of {assessment.questions.length}
            </b>
            <span>
              {Math.round((ready / assessment.questions.length) * 100)}%
            </span>
          </div>
          <LinearProgress
            className="mt-2"
            variant="determinate"
            value={(ready / assessment.questions.length) * 100}
          />
          <div className="mt-4 flex flex-wrap gap-2">
            {assessment.questions.map((question, index) => (
              <button
                key={question.id}
                onClick={() => navigate(index)}
                className={`h-9 min-w-9 rounded-lg border px-2 text-sm font-bold ${index === current ? "border-red-700 bg-red-700 text-white" : question.ready ? "border-green-300 bg-green-50 text-green-800" : "border-slate-300 bg-white text-slate-500"}`}
              >
                {index + 1}
                {question.ready ? " ✓" : " —"}
              </button>
            ))}
          </div>
        </section>
        {selected && (
          <section className="rounded-2xl border bg-white p-5 shadow-sm">
            <div className="mb-5 flex items-center justify-between">
              <h2 className="font-bold">
                Question {current + 1} of {assessment.questions.length}
              </h2>
              <span className="text-sm text-slate-500">
                {form.processing
                  ? "Saving…"
                  : form.recentlySuccessful
                    ? "Saved"
                    : selected.ready
                      ? "Ready"
                      : "Not completed"}
              </span>
            </div>
            <div className="grid gap-4">
              <TextField
                select
                label="Question Type"
                value={form.data.question_type}
                onChange={(e) => changeType(e.target.value)}
              >
                {[
                  ["multiple_choice", "Multiple Choice"],
                  ["true_false", "True / False"],
                  ["multiple_selection", "Multiple Selection"],
                  ["short_answer", "Short Answer"],
                ].map(([value, label]) => (
                  <MenuItem key={value} value={value}>
                    {label}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label="Question"
                multiline
                minRows={3}
                value={form.data.question_text}
                error={Boolean(form.errors.question_text)}
                helperText={form.errors.question_text}
                onChange={(e) => form.setData("question_text", e.target.value)}
              />
              {form.data.question_type === "short_answer" ? (
                <Alert severity="info">
                  Requires Manual Review. Use Feedback / Explanation for grading
                  guidance or expected-answer notes.
                </Alert>
              ) : (
                <div className="space-y-3">
                  <p className="text-sm font-bold">
                    Answer Choices{" "}
                    {form.data.question_type === "multiple_selection" &&
                      "· Select every correct answer"}
                  </p>
                  {form.data.options.map((option, index) => (
                    <div className="flex items-center gap-2" key={index}>
                      {form.data.question_type === "multiple_selection" ? (
                        <Checkbox
                          checked={option.is_correct}
                          onChange={(_, value) => setCorrect(index, value)}
                        />
                      ) : (
                        <Radio
                          checked={option.is_correct}
                          onChange={() => setCorrect(index, true)}
                        />
                      )}
                      <TextField
                        fullWidth
                        size="small"
                        disabled={form.data.question_type === "true_false"}
                        label={`Choice ${String.fromCharCode(65 + index)}`}
                        value={option.option_text}
                        onChange={(e) =>
                          form.setData(
                            "options",
                            form.data.options.map((item, itemIndex) =>
                              itemIndex === index
                                ? { ...item, option_text: e.target.value }
                                : item,
                            ),
                          )
                        }
                      />
                      {form.data.question_type !== "true_false" &&
                        form.data.options.length > 2 && (
                          <Button
                            color="error"
                            onClick={() =>
                              form.setData(
                                "options",
                                form.data.options.filter(
                                  (_, itemIndex) => itemIndex !== index,
                                ),
                              )
                            }
                          >
                            Remove
                          </Button>
                        )}
                    </div>
                  ))}
                  {form.data.question_type !== "true_false" &&
                    form.data.options.length < 20 && (
                      <Button
                        onClick={() =>
                          form.setData("options", [
                            ...form.data.options,
                            { option_text: "", is_correct: false },
                          ])
                        }
                      >
                        Add Choice
                      </Button>
                    )}
                </div>
              )}
              {form.errors.options && (
                <Alert severity="error">{form.errors.options}</Alert>
              )}
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  type="number"
                  label="Points"
                  value={form.data.points}
                  onChange={(e) => form.setData("points", e.target.value)}
                />
                <TextField
                  select
                  label="Skill (optional)"
                  value={form.data.skill_id}
                  onChange={(e) => form.setData("skill_id", e.target.value)}
                >
                  <MenuItem value="">No skill</MenuItem>
                  {skills.map((skill) => (
                    <MenuItem key={skill.id} value={skill.id}>
                      {skill.name}
                    </MenuItem>
                  ))}
                </TextField>
              </div>
              <TextField
                label="Feedback / Explanation (optional)"
                multiline
                minRows={2}
                value={form.data.feedback}
                onChange={(e) => form.setData("feedback", e.target.value)}
              />
              <div className="flex flex-wrap justify-between gap-2">
                <div>
                  <Button
                    disabled={current === 0 || form.processing}
                    onClick={() => navigate(current - 1)}
                  >
                    Previous
                  </Button>
                  <Button
                    disabled={
                      current === assessment.questions.length - 1 ||
                      form.processing
                    }
                    onClick={() => navigate(current + 1)}
                  >
                    Next
                  </Button>
                </div>
                <div className="flex gap-2">
                  <Link
                    href={`/management/assessments/${assessment.id}/simple-question-bank?slot=${selected.id}&type=${form.data.question_type}`}
                  >
                    <Button>Choose from Question Bank</Button>
                  </Link>
                  <Button
                    variant="outlined"
                    disabled={form.processing}
                    onClick={() => save(false)}
                  >
                    Save
                  </Button>
                  <Button
                    variant="contained"
                    disabled={form.processing}
                    onClick={() => save(true)}
                  >
                    Save & Next
                  </Button>
                </div>
              </div>
            </div>
          </section>
        )}
      </div>
    </main>
  );
}

function Review({
  assessment,
  readinessErrors,
  campaign,
  training,
  total,
}: {
  assessment: Assessment;
  readinessErrors: string[];
  campaign: string;
  training: string[];
  total: number;
}) {
  return (
    <main className="min-h-full bg-[#f7f7fa] p-4 lg:p-6">
      <Head title={`Review ${assessment.title}`} />
      <div className="mx-auto max-w-4xl space-y-5">
        <header className="flex justify-between gap-3">
          <div>
            <p className="text-xs font-bold tracking-[.18em] text-[#b72822] uppercase">
              Review Assessment
            </p>
            <h1 className="text-2xl font-bold">{assessment.title}</h1>
          </div>
          <Link
            href={`/management/assessments/${assessment.id}/simple-builder`}
          >
            <Button>Edit Questions</Button>
          </Link>
        </header>
        {readinessErrors.length > 0 ? (
          <Alert severity="warning">
            <b>Assessment isn&apos;t ready yet</b>
            <ul className="mt-2 list-disc pl-5">
              {readinessErrors.map((error) => (
                <li key={error}>{error}</li>
              ))}
            </ul>
          </Alert>
        ) : (
          <Alert severity="success">Assessment is ready to publish.</Alert>
        )}
        <section className="grid gap-3 rounded-2xl border bg-white p-5 shadow-sm sm:grid-cols-3">
          <Summary label="Campaign" value={campaign} />
          <Summary label="Training" value={training.join(", ") || "None"} />
          <Summary
            label="Questions"
            value={String(assessment.questions.length)}
          />
          <Summary label="Total Points" value={String(total)} />
          <Summary
            label="Passing Score"
            value={`${assessment.passing_score}%`}
          />
          <Summary
            label="Time Limit"
            value={
              assessment.time_limit_minutes
                ? `${assessment.time_limit_minutes} minutes`
                : "No limit"
            }
          />
          <Summary
            label="Attempts"
            value={String(assessment.maximum_attempts)}
          />
        </section>
        <section className="space-y-2">
          {assessment.questions.map((question, index) => (
            <article
              key={question.id}
              className="flex items-start justify-between rounded-xl border bg-white p-4"
            >
              <div>
                <b>
                  {index + 1}.{" "}
                  {question.question_text || "Question not completed"}
                </b>
                <p className="mt-1 text-sm text-slate-500">
                  {question.question_type.replaceAll("_", " ")} ·{" "}
                  {question.points} points ·{" "}
                  {question.skill?.name || "No skill"} ·{" "}
                  {question.ready ? "Ready" : "Needs Attention"}
                </p>
              </div>
              <Link
                href={`/management/assessments/${assessment.id}/simple-builder`}
              >
                <Button
                  onClick={() =>
                    sessionStorage.setItem(
                      `assessment-${assessment.id}-question`,
                      String(index),
                    )
                  }
                >
                  Edit
                </Button>
              </Link>
            </article>
          ))}
        </section>
        <div className="flex justify-end gap-2">
          <Link href={`/management/assessments/${assessment.id}/builder`}>
            <Button variant="outlined">Advanced Builder</Button>
          </Link>
          <Button
            variant="contained"
            disabled={readinessErrors.length > 0}
            onClick={() =>
              window.confirm("Publish this assessment now?") &&
              router.patch(`/management/assessments/${assessment.id}/publish`)
            }
          >
            Publish Now
          </Button>
          <Link
            href={`/management/assessments/${assessment.id}/simple-schedule`}
          >
            <Button variant="outlined" disabled={readinessErrors.length > 0}>
              Schedule
            </Button>
          </Link>
        </div>
      </div>
    </main>
  );
}
function Summary({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs font-bold tracking-wide text-slate-500 uppercase">
        {label}
      </p>
      <p className="mt-1 font-semibold">{value}</p>
    </div>
  );
}
SimpleBuilder.layout = {
  breadcrumbs: [
    { title: "Assessments", href: "/management/assessments" },
    { title: "Simple Builder", href: "#" },
  ],
};
