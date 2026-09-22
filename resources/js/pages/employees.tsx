import { Head, router } from '@inertiajs/react';
import {
    Alert,
    Box,
    Button,
    Checkbox,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    Divider,
    FormControlLabel,
    IconButton,
    MenuItem,
    Stack,
    Step,
    StepLabel,
    Stepper,
    Tab,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import { useEffect, useRef, useState } from 'react';
import {
    BriefcaseBusiness,
    Camera,
    CameraOff,
    ChevronLeft,
    ChevronRight,
    Download,
    Filter,
    HeartHandshake,
    Phone,
    Plus,
    ShieldCheck,
    Sparkles,
    UserPlus,
    X,
    RefreshCw,
    Search,
    ScanFace,
    UserCheck,
    Users,
    UserRound,
    UserX,
} from 'lucide-react';

type EmployeesProps = {
    nextEmployeeId: string;
    employees: Employee[];
    stats: EmployeeStats;
    canManageEmployees: boolean;
};

type EmployeeStats = {
    total: number;
    active: number;
    onLeave: number;
    inactive: number;
};

type Employee = {
    id: number;
    employeeId: string;
    name: string;
    email: string;
    position: string;
    status: 'active' | 'on_leave' | 'inactive' | string;
    team: string | null;
    schedule: string | null;
    personalInformation: EmployeePersonalInfo | null;
};

type PersonalInfo = {
    birthDate: string;
    startDate: string;
    gender: string;
    civilStatus: string;
    phone: string;
    email: string;
    address: string;
    emergencyName: string;
    emergencyRelationship: string;
    emergencyPhone: string;
    emergencyAddress: string;
};

type EmployeePersonalInfo = Omit<PersonalInfo, 'emergencyAddress'> & {
    emergencyAddress: string | null;
};

type EmployeeEditForm = PersonalInfo & {
    fullName: string;
    position: string;
    status: string;
    newPassword: string;
};

type CameraStatus = 'idle' | 'requesting' | 'active' | 'denied';
type FaceDetectionStatus =
    'idle' | 'loading' | 'scanning' | 'submitting' | 'error';

const positions = [
    'Admin',
    'Team Leader',
    'Agent',
    'IT Admin',
    'IT Support',
    'IT Developer',
];

const initialsFor = (name: string) =>
    name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

const formatDate = (value?: string) =>
    value
        ? new Intl.DateTimeFormat('en-US', {
              month: 'short',
              day: '2-digit',
              year: 'numeric',
          }).format(new Date(`${value}T00:00:00`))
        : 'Not provided';

export default function Employees({
    nextEmployeeId,
    employees,
    stats,
    canManageEmployees,
}: EmployeesProps) {
    const [createEmployeeOpen, setCreateEmployeeOpen] = useState(false);
    const [selectedEmployee, setSelectedEmployee] = useState<Employee | null>(
        null,
    );
    const [employeeModalTab, setEmployeeModalTab] = useState(0);
    const [employeeEditForm, setEmployeeEditForm] =
        useState<EmployeeEditForm | null>(null);
    const [employeeEditErrors, setEmployeeEditErrors] = useState<
        Record<string, string>
    >({});
    const [employeeEditProcessing, setEmployeeEditProcessing] = useState(false);
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [currentEmployeePage, setCurrentEmployeePage] = useState(1);
    const [roleFilter, setRoleFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState<
        'all' | 'active' | 'suspended' | 'floating' | 'resigned' | 'terminated'
    >('all');
    const [employeeStep, setEmployeeStep] = useState(0);
    const [cameraStatus, setCameraStatus] = useState<CameraStatus>('idle');
    const [faceDetectionStatus, setFaceDetectionStatus] =
        useState<FaceDetectionStatus>('idle');
    const [faceConsent, setFaceConsent] = useState(false);
    const [faceDetectionMessage, setFaceDetectionMessage] = useState('');
    const filteredEmployees = employees.filter((employee) => {
        const query = employeeSearch.trim().toLowerCase();

        return (
            (!query ||
                employee.name.toLowerCase().includes(query) ||
                employee.employeeId.toLowerCase().includes(query)) &&
            (statusFilter === 'all' || employee.status === statusFilter) &&
            (roleFilter === 'all' || employee.position === roleFilter)
        );
    });
    const employeesPerPage = 20;
    const totalEmployeePages = Math.ceil(
        filteredEmployees.length / employeesPerPage,
    );
    const paginatedEmployees = filteredEmployees.slice(
        (currentEmployeePage - 1) * employeesPerPage,
        currentEmployeePage * employeesPerPage,
    );
    const firstVisibleEmployee = filteredEmployees.length
        ? (currentEmployeePage - 1) * employeesPerPage + 1
        : 0;
    const lastVisibleEmployee = Math.min(
        currentEmployeePage * employeesPerPage,
        filteredEmployees.length,
    );
    const statCards = [
        {
            label: 'Total Employees',
            value: stats.total,
            note: 'Registered accounts',
            icon: Users,
            color: 'bg-[#fff0ee] text-[#c52c22]',
        },
        {
            label: 'Active',
            value: stats.active,
            note: `${stats.total ? Math.round((stats.active / stats.total) * 100) : 0}% of workforce`,
            icon: UserCheck,
            color: 'bg-emerald-50 text-emerald-600',
        },
        {
            label: 'Floating',
            value: stats.onLeave,
            note: 'Current database status',
            icon: BriefcaseBusiness,
            color: 'bg-amber-50 text-amber-600',
        },
        {
            label: 'Login Disabled',
            value: stats.inactive,
            note: 'Current database status',
            icon: UserX,
            color: 'bg-slate-100 text-slate-500',
        },
    ];
    const videoRef = useRef<HTMLVideoElement>(null);
    const cameraStreamRef = useRef<MediaStream | null>(null);
    const [fullName, setFullName] = useState('');
    const [position, setPosition] = useState('');
    const [personalInfo, setPersonalInfo] = useState<PersonalInfo>({
        birthDate: '',
        startDate: '',
        gender: '',
        civilStatus: '',
        phone: '',
        email: '',
        address: '',
        emergencyName: '',
        emergencyRelationship: '',
        emergencyPhone: '',
        emergencyAddress: '',
    });

    const updatePersonalInfo = (field: keyof PersonalInfo, value: string) => {
        setPersonalInfo((current) => ({ ...current, [field]: value }));
    };

    const personalInfoComplete =
        personalInfo.birthDate &&
        personalInfo.startDate &&
        personalInfo.gender &&
        personalInfo.civilStatus &&
        personalInfo.phone.trim() &&
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(personalInfo.email) &&
        personalInfo.address.trim() &&
        personalInfo.emergencyName.trim() &&
        personalInfo.emergencyRelationship.trim() &&
        personalInfo.emergencyPhone.trim();

    const stopCamera = () => {
        cameraStreamRef.current?.getTracks().forEach((track) => track.stop());
        cameraStreamRef.current = null;

        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }
    };

    const startCamera = async () => {
        if (!faceConsent) {
            setFaceDetectionStatus('error');
            setFaceDetectionMessage(
                'Confirm the employee consent before starting face enrollment.',
            );
            return;
        }

        setFaceDetectionStatus('idle');
        setFaceDetectionMessage('');
        setCameraStatus('requesting');
        stopCamera();

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
            });

            cameraStreamRef.current = stream;

            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                await videoRef.current.play();
            }

            setCameraStatus('active');
            void detectAndSubmitFace();
        } catch {
            setCameraStatus('denied');
        }
    };

    const detectAndSubmitFace = async () => {
        if (!videoRef.current || !faceConsent) {
            return;
        }

        setFaceDetectionStatus('loading');
        setFaceDetectionMessage('Loading secure face models…');

        try {
            const { default: Human } = await import('@vladmandic/human');
            const human = new Human({
                backend: 'webgl',
                modelBasePath: '/models/human/',
                cacheSensitivity: 0,
                face: {
                    enabled: true,
                    detector: {
                        enabled: true,
                        rotation: true,
                        maxDetected: 2,
                        minConfidence: 0.7,
                    },
                    mesh: { enabled: true },
                    description: { enabled: true },
                    antispoof: { enabled: true },
                    liveness: { enabled: true },
                    emotion: { enabled: false },
                    iris: { enabled: false },
                    attention: { enabled: false },
                    gear: { enabled: false },
                },
                body: { enabled: false },
                hand: { enabled: false },
                object: { enabled: false },
                gesture: { enabled: false },
                segmentation: { enabled: false },
            });

            await human.load();
            setFaceDetectionStatus('scanning');
            setFaceDetectionMessage(
                'Center one face in the guide and look at the camera.',
            );

            let acceptedFrames = 0;

            for (let attempt = 0; attempt < 45; attempt += 1) {
                if (!videoRef.current || !cameraStreamRef.current) {
                    return;
                }

                const result = await human.detect(videoRef.current);
                const face = result.face[0];

                if (result.face.length !== 1) {
                    acceptedFrames = 0;
                    setFaceDetectionMessage(
                        result.face.length > 1
                            ? 'Only one person may be visible.'
                            : 'No face detected. Move into the guide.',
                    );
                } else if (
                    !face.embedding ||
                    face.embedding.length < 128 ||
                    (face.real ?? 0) < 0.5 ||
                    (face.live ?? 0) < 0.5
                ) {
                    acceptedFrames = 0;
                    setFaceDetectionMessage(
                        'Hold still and use even lighting. Checking liveness…',
                    );
                } else {
                    acceptedFrames += 1;
                    setFaceDetectionMessage(
                        `Face verified. Hold still ${Math.max(0, 3 - acceptedFrames)}…`,
                    );

                    if (acceptedFrames >= 3) {
                        stopCamera();
                        setFaceDetectionStatus('submitting');
                        setFaceDetectionMessage(
                            'Face verified. Creating employee account…',
                        );

                        router.post(
                            '/employees',
                            {
                                full_name: fullName.trim(),
                                position,
                                email: personalInfo.email.trim(),
                                birth_date: personalInfo.birthDate,
                                start_date: personalInfo.startDate,
                                gender: personalInfo.gender,
                                civil_status: personalInfo.civilStatus,
                                phone: personalInfo.phone.trim(),
                                address: personalInfo.address.trim(),
                                emergency_contact_name:
                                    personalInfo.emergencyName.trim(),
                                emergency_contact_relationship:
                                    personalInfo.emergencyRelationship.trim(),
                                emergency_contact_phone:
                                    personalInfo.emergencyPhone.trim(),
                                emergency_contact_address:
                                    personalInfo.emergencyAddress.trim() ||
                                    null,
                                face_descriptor: face.embedding,
                                face_liveness: face.live,
                                face_antispoof: face.real,
                                face_model_version: 'human-3.3.6-faceres',
                                face_consent: true,
                            },
                            {
                                preserveScroll: true,
                                onError: (errors) => {
                                    setFaceDetectionStatus('error');
                                    setFaceDetectionMessage(
                                        Object.values(errors)[0] ??
                                            'Unable to create employee.',
                                    );
                                },
                                onSuccess: () => {
                                    setCreateEmployeeOpen(false);
                                    setEmployeeStep(0);
                                },
                            },
                        );

                        return;
                    }
                }

                await new Promise((resolve) => setTimeout(resolve, 180));
            }

            setFaceDetectionStatus('error');
            setFaceDetectionMessage(
                'Face verification timed out. Check lighting and try again.',
            );
        } catch {
            setFaceDetectionStatus('error');
            setFaceDetectionMessage(
                'The face models could not start. Refresh and try again.',
            );
        }
    };

    useEffect(() => {
        if (!createEmployeeOpen || employeeStep !== 2) {
            stopCamera();
            setCameraStatus('idle');
            setFaceDetectionStatus('idle');
            setFaceDetectionMessage('');
        }

        return stopCamera;
    }, [createEmployeeOpen, employeeStep]);

    useEffect(() => {
        if (!selectedEmployee) {
            setEmployeeEditForm(null);
            return;
        }

        const details = selectedEmployee.personalInformation;
        setEmployeeModalTab(0);
        setEmployeeEditErrors({});
        setEmployeeEditForm({
            fullName: selectedEmployee.name,
            position: selectedEmployee.position,
            status: selectedEmployee.status,
            newPassword: '',
            email: details?.email ?? selectedEmployee.email,
            birthDate: details?.birthDate ?? '',
            startDate: details?.startDate ?? '',
            gender: details?.gender ?? '',
            civilStatus: details?.civilStatus ?? '',
            phone: details?.phone ?? '',
            address: details?.address ?? '',
            emergencyName: details?.emergencyName ?? '',
            emergencyRelationship: details?.emergencyRelationship ?? '',
            emergencyPhone: details?.emergencyPhone ?? '',
            emergencyAddress: details?.emergencyAddress ?? '',
        });
    }, [selectedEmployee]);

    useEffect(() => {
        setCurrentEmployeePage(1);
    }, [employeeSearch, roleFilter, statusFilter]);

    useEffect(() => {
        if (
            totalEmployeePages > 0 &&
            currentEmployeePage > totalEmployeePages
        ) {
            setCurrentEmployeePage(totalEmployeePages);
        }
    }, [currentEmployeePage, totalEmployeePages]);

    const updateEmployeeEditField = (
        field: keyof EmployeeEditForm,
        value: string,
    ) => {
        setEmployeeEditForm((current) =>
            current ? { ...current, [field]: value } : current,
        );
    };

    const saveEmployeeChanges = () => {
        if (!selectedEmployee || !employeeEditForm) {
            return;
        }

        setEmployeeEditProcessing(true);
        setEmployeeEditErrors({});
        router.put(
            `/employees/${selectedEmployee.id}`,
            {
                full_name: employeeEditForm.fullName.trim(),
                position: employeeEditForm.position,
                status: employeeEditForm.status,
                new_password: employeeEditForm.newPassword || null,
                email: employeeEditForm.email.trim(),
                birth_date: employeeEditForm.birthDate,
                start_date: employeeEditForm.startDate,
                gender: employeeEditForm.gender,
                civil_status: employeeEditForm.civilStatus,
                phone: employeeEditForm.phone.trim(),
                address: employeeEditForm.address.trim(),
                emergency_contact_name: employeeEditForm.emergencyName.trim(),
                emergency_contact_relationship:
                    employeeEditForm.emergencyRelationship.trim(),
                emergency_contact_phone: employeeEditForm.emergencyPhone.trim(),
                emergency_contact_address:
                    employeeEditForm.emergencyAddress.trim() || null,
            },
            {
                preserveScroll: true,
                onError: (errors) => setEmployeeEditErrors(errors),
                onSuccess: () => setSelectedEmployee(null),
                onFinish: () => setEmployeeEditProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title="Employees" />

            <main className="min-h-full bg-[#f7f7fa] p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1500px] space-y-7">
                    <header className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                        <div>
                            <p className="mb-2 text-xs font-bold tracking-[0.2em] text-[#b72822] uppercase">
                                Workforce Management
                            </p>
                            <h1 className="text-3xl font-bold tracking-[-0.03em] text-[#1b1d2a] sm:text-4xl">
                                Employees
                            </h1>
                            <p className="mt-2 text-sm text-[#777b8e] sm:text-base">
                                View and manage your organization&apos;s
                                employee directory.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <button
                                type="button"
                                className="inline-flex h-11 items-center gap-2 rounded-xl border border-[#dedfe6] bg-white px-4 text-sm font-semibold text-[#444759] shadow-sm transition hover:border-[#c8cad4] hover:bg-[#fafafa]"
                            >
                                <Download className="size-4" />
                                Export
                            </button>
                            <Button
                                variant="contained"
                                startIcon={<Plus className="size-4" />}
                                onClick={() => setCreateEmployeeOpen(true)}
                            >
                                Add Employee
                            </Button>
                        </div>
                    </header>

                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {statCards.map((stat) => {
                            const Icon = stat.icon;

                            return (
                                <article
                                    key={stat.label}
                                    className="rounded-2xl border border-[#e9e9ee] bg-white p-5 shadow-[0_8px_30px_rgba(22,24,35,0.04)]"
                                >
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-[#7a7e90]">
                                                {stat.label}
                                            </p>
                                            <p className="mt-2 text-3xl font-bold tracking-tight text-[#202230]">
                                                {stat.value}
                                            </p>
                                        </div>
                                        <div
                                            className={`flex size-11 items-center justify-center rounded-xl ${stat.color}`}
                                        >
                                            <Icon className="size-5" />
                                        </div>
                                    </div>
                                    <p className="mt-3 text-xs font-medium text-[#9a9dad]">
                                        {stat.note}
                                    </p>
                                </article>
                            );
                        })}
                    </section>

                    <section className="overflow-hidden rounded-3xl border border-[#e6e7ec] bg-white shadow-[0_16px_50px_rgba(25,27,38,0.06)]">
                        <div className="border-b border-[#ededf1] p-5 sm:p-6">
                            <div className="flex flex-col justify-between gap-4 xl:flex-row xl:items-center">
                                <div>
                                    <h2 className="text-lg font-bold text-[#202230]">
                                        Employee Directory
                                    </h2>
                                    <p className="mt-1 text-sm text-[#888b9b]">
                                        Showing all registered employee accounts
                                    </p>
                                </div>

                                <div className="flex flex-col gap-3 sm:flex-row">
                                    <label className="relative block min-w-0 sm:w-72">
                                        <Search className="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[#a1a4b2]" />
                                        <input
                                            type="search"
                                            value={employeeSearch}
                                            onChange={(event) =>
                                                setEmployeeSearch(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Search name or employee ID"
                                            className="h-11 w-full rounded-xl border border-[#e1e2e8] bg-[#fafafd] pr-4 pl-11 text-sm text-[#303240] outline-none placeholder:text-[#a1a4b2] focus:border-[#bd352a] focus:ring-3 focus:ring-[#bd352a]/10"
                                        />
                                    </label>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setStatusFilter((current) => {
                                                const filters = [
                                                    'all',
                                                    'active',
                                                    'suspended',
                                                    'floating',
                                                    'resigned',
                                                    'terminated',
                                                ] as const;
                                                return filters[
                                                    (filters.indexOf(current) +
                                                        1) %
                                                        filters.length
                                                ];
                                            })
                                        }
                                        className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[#e1e2e8] bg-white px-4 text-sm font-semibold text-[#555869] hover:bg-[#fafafd]"
                                    >
                                        <Filter className="size-4" />
                                        {statusFilter === 'all'
                                            ? 'All statuses'
                                            : statusFilter
                                                  .charAt(0)
                                                  .toUpperCase() +
                                              statusFilter.slice(1)}
                                    </button>
                                    <TextField
                                        select
                                        size="small"
                                        value={roleFilter}
                                        onChange={(event) =>
                                            setRoleFilter(event.target.value)
                                        }
                                        aria-label="Filter employees by role"
                                        slotProps={{
                                            select: {
                                                displayEmpty: true,
                                            },
                                        }}
                                        sx={{
                                            minWidth: 150,
                                            '& .MuiOutlinedInput-root': {
                                                height: 44,
                                                borderRadius: 3,
                                                bgcolor: 'common.white',
                                            },
                                        }}
                                    >
                                        <MenuItem value="all">
                                            All Roles
                                        </MenuItem>
                                        {positions.map((role) => (
                                            <MenuItem key={role} value={role}>
                                                {role}
                                            </MenuItem>
                                        ))}
                                    </TextField>
                                    <button
                                        type="button"
                                        aria-label="Refresh employees"
                                        onClick={() =>
                                            router.reload({
                                                only: ['employees', 'stats'],
                                            })
                                        }
                                        className="inline-flex size-11 items-center justify-center rounded-xl border border-[#e1e2e8] bg-white text-[#555869] hover:bg-[#fafafd]"
                                    >
                                        <RefreshCw className="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1050px] border-collapse text-left">
                                <thead>
                                    <tr className="bg-[#fafafd] text-[11px] font-bold tracking-[0.08em] text-[#8b8e9e] uppercase">
                                        <th className="px-6 py-4">Employee</th>
                                        <th className="px-5 py-4">
                                            Employee ID
                                        </th>
                                        <th className="px-5 py-4">Position</th>
                                        <th className="px-5 py-4">Team</th>
                                        <th className="px-5 py-4">Schedule</th>
                                        <th className="px-5 py-4">
                                            Start Date
                                        </th>
                                        <th className="px-5 py-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#eff0f3]">
                                    {paginatedEmployees.map(
                                        (employee, index) => (
                                            <tr
                                                key={employee.id}
                                                tabIndex={0}
                                                role="button"
                                                onClick={() =>
                                                    setSelectedEmployee(
                                                        employee,
                                                    )
                                                }
                                                onKeyDown={(event) => {
                                                    if (
                                                        event.key === 'Enter' ||
                                                        event.key === ' '
                                                    ) {
                                                        event.preventDefault();
                                                        setSelectedEmployee(
                                                            employee,
                                                        );
                                                    }
                                                }}
                                                className="group cursor-pointer transition hover:bg-[#fcfaf9] focus:bg-[#fcfaf9] focus:outline-none"
                                            >
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div
                                                            className={`flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${['from-emerald-500 to-amber-400', 'from-sky-500 to-indigo-500', 'from-fuchsia-500 to-rose-400', 'from-orange-500 to-red-500', 'from-violet-500 to-blue-500'][index % 5]} text-sm font-bold text-white shadow-sm`}
                                                        >
                                                            {initialsFor(
                                                                employee.name,
                                                            )}
                                                        </div>
                                                        <div>
                                                            <p className="font-semibold text-[#252735]">
                                                                {employee.name}
                                                            </p>
                                                            <p className="mt-0.5 text-xs text-[#9295a4]">
                                                                {employee.email}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span className="rounded-lg bg-[#fff1ef] px-2.5 py-1.5 font-mono text-xs font-bold text-[#bd3028]">
                                                        {employee.employeeId}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-4 text-sm font-medium text-[#4d5060]">
                                                    {employee.position}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span className="inline-flex rounded-full bg-[#eef6ff] px-3 py-1.5 text-xs font-semibold text-[#3472b9]">
                                                        {employee.team ??
                                                            'Not assigned'}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-4 text-sm text-[#626576]">
                                                    {employee.schedule ??
                                                        'Not assigned'}
                                                </td>
                                                <td className="px-5 py-4 text-sm text-[#626576]">
                                                    {formatDate(
                                                        employee
                                                            .personalInformation
                                                            ?.startDate,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span
                                                        className={`inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ${
                                                            employee.status ===
                                                            'active'
                                                                ? 'bg-emerald-50 text-emerald-700'
                                                                : employee.status ===
                                                                    'floating'
                                                                  ? 'bg-amber-50 text-amber-700'
                                                                  : 'bg-red-50 text-red-700'
                                                        }`}
                                                    >
                                                        <span
                                                            className={`size-1.5 rounded-full ${
                                                                employee.status ===
                                                                'active'
                                                                    ? 'bg-emerald-500'
                                                                    : employee.status ===
                                                                        'floating'
                                                                      ? 'bg-amber-500'
                                                                      : 'bg-red-500'
                                                            }`}
                                                        />
                                                        {employee.status
                                                            .charAt(0)
                                                            .toUpperCase() +
                                                            employee.status.slice(
                                                                1,
                                                            )}
                                                    </span>
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <footer className="flex flex-col items-center justify-between gap-4 border-t border-[#ededf1] px-6 py-4 sm:flex-row">
                            <p className="text-sm text-[#888b9b]">
                                Showing{' '}
                                <span className="font-semibold text-[#363846]">
                                    {firstVisibleEmployee}–{lastVisibleEmployee}
                                </span>{' '}
                                of{' '}
                                <span className="font-semibold text-[#363846]">
                                    {filteredEmployees.length}
                                </span>{' '}
                                employees
                            </p>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    aria-label="Previous page"
                                    disabled={currentEmployeePage === 1}
                                    onClick={() =>
                                        setCurrentEmployeePage((page) =>
                                            Math.max(1, page - 1),
                                        )
                                    }
                                    className="inline-flex size-9 items-center justify-center rounded-lg border border-[#e1e2e8] text-[#555868] transition hover:bg-[#f8f8fa] disabled:cursor-not-allowed disabled:text-[#c5c7d0] disabled:hover:bg-white"
                                >
                                    <ChevronLeft className="size-4" />
                                </button>
                                {Array.from(
                                    { length: totalEmployeePages },
                                    (_, index) => index + 1,
                                ).map((page) => (
                                    <button
                                        key={page}
                                        type="button"
                                        aria-current={
                                            page === currentEmployeePage
                                                ? 'page'
                                                : undefined
                                        }
                                        onClick={() =>
                                            setCurrentEmployeePage(page)
                                        }
                                        className={`size-9 rounded-lg text-sm font-semibold transition ${
                                            page === currentEmployeePage
                                                ? 'bg-[#a92420] text-white'
                                                : 'text-[#666979] hover:bg-[#f3f3f6]'
                                        }`}
                                    >
                                        {page}
                                    </button>
                                ))}
                                <button
                                    type="button"
                                    aria-label="Next page"
                                    disabled={
                                        totalEmployeePages === 0 ||
                                        currentEmployeePage ===
                                            totalEmployeePages
                                    }
                                    onClick={() =>
                                        setCurrentEmployeePage((page) =>
                                            Math.min(
                                                totalEmployeePages,
                                                page + 1,
                                            ),
                                        )
                                    }
                                    className="inline-flex size-9 items-center justify-center rounded-lg border border-[#e1e2e8] text-[#555868] transition hover:bg-[#f8f8fa] disabled:cursor-not-allowed disabled:text-[#c5c7d0] disabled:hover:bg-white"
                                >
                                    <ChevronRight className="size-4" />
                                </button>
                            </div>
                        </footer>
                    </section>
                </div>
            </main>

            <Dialog
                open={selectedEmployee !== null}
                onClose={() => setSelectedEmployee(null)}
                fullWidth
                maxWidth="md"
                slotProps={{
                    paper: {
                        sx: {
                            overflow: 'hidden',
                            borderRadius: 3,
                            m: { xs: 1.5, sm: 3 },
                        },
                    },
                }}
            >
                {selectedEmployee && (
                    <>
                        <Box
                            sx={{
                                position: 'relative',
                                px: { xs: 2.5, sm: 3.5 },
                                py: 3,
                                pr: { xs: 9, sm: 11 },
                                color: 'common.white',
                                background:
                                    'linear-gradient(125deg, #251525 0%, #681e26 55%, #b42b23 100%)',
                            }}
                        >
                            <Stack
                                direction="row"
                                spacing={2}
                                sx={{ alignItems: 'center' }}
                            >
                                <Stack
                                    direction="row"
                                    spacing={2}
                                    sx={{ alignItems: 'center' }}
                                >
                                    <Box
                                        sx={{
                                            display: 'grid',
                                            placeItems: 'center',
                                            width: 54,
                                            height: 54,
                                            borderRadius: 2.5,
                                            bgcolor: 'rgba(255,255,255,.14)',
                                            border: '1px solid rgba(255,255,255,.2)',
                                            fontWeight: 800,
                                        }}
                                    >
                                        {initialsFor(selectedEmployee.name)}
                                    </Box>
                                    <Box>
                                        <Typography
                                            variant="h5"
                                            sx={{ fontWeight: 800 }}
                                        >
                                            {selectedEmployee.name}
                                        </Typography>
                                        <Typography
                                            variant="body2"
                                            sx={{
                                                color: 'rgba(255,255,255,.72)',
                                            }}
                                        >
                                            {selectedEmployee.employeeId} ·{' '}
                                            {selectedEmployee.position}
                                        </Typography>
                                    </Box>
                                </Stack>
                            </Stack>
                            <IconButton
                                aria-label="Close employee information"
                                onClick={() => setSelectedEmployee(null)}
                                sx={{
                                    position: 'absolute',
                                    top: { xs: 20, sm: 24 },
                                    right: { xs: 20, sm: 28 },
                                    width: 44,
                                    height: 44,
                                    color: 'common.white',
                                    bgcolor: 'rgba(255,255,255,.14)',
                                    border: '1px solid rgba(255,255,255,.08)',
                                    '&:hover': {
                                        bgcolor: 'rgba(255,255,255,.24)',
                                    },
                                }}
                            >
                                <X size={21} />
                            </IconButton>
                        </Box>

                        <Tabs
                            value={employeeModalTab}
                            onChange={(_, value: number) =>
                                setEmployeeModalTab(value)
                            }
                            sx={{
                                px: { xs: 2.5, sm: 3.5 },
                                borderBottom: '1px solid',
                                borderColor: 'divider',
                            }}
                        >
                            <Tab label="Personal Information" />
                            {canManageEmployees && (
                                <Tab label="Account & Edit" />
                            )}
                        </Tabs>

                        <DialogContent
                            sx={{ px: { xs: 2.5, sm: 3.5 }, py: 3.5 }}
                        >
                            {employeeModalTab === 0 ? (
                                selectedEmployee.personalInformation ? (
                                    <Stack spacing={3}>
                                        <Box>
                                            <Typography
                                                variant="subtitle1"
                                                sx={{ fontWeight: 800 }}
                                            >
                                                Personal Information
                                            </Typography>
                                            <Typography
                                                variant="body2"
                                                color="text.secondary"
                                            >
                                                Employee contact and identity
                                                details
                                            </Typography>
                                        </Box>
                                        <Box
                                            sx={{
                                                display: 'grid',
                                                gridTemplateColumns: {
                                                    xs: '1fr',
                                                    sm: 'repeat(2, 1fr)',
                                                },
                                                gap: 2,
                                            }}
                                        >
                                            {[
                                                [
                                                    'Email address',
                                                    selectedEmployee
                                                        .personalInformation
                                                        .email,
                                                ],
                                                [
                                                    'Phone number',
                                                    selectedEmployee
                                                        .personalInformation
                                                        .phone,
                                                ],
                                                [
                                                    'Birth date',
                                                    formatDate(
                                                        selectedEmployee
                                                            .personalInformation
                                                            .birthDate,
                                                    ),
                                                ],
                                                [
                                                    'Start date',
                                                    formatDate(
                                                        selectedEmployee
                                                            .personalInformation
                                                            .startDate,
                                                    ),
                                                ],
                                                [
                                                    'Gender',
                                                    selectedEmployee
                                                        .personalInformation
                                                        .gender,
                                                ],
                                                [
                                                    'Civil status',
                                                    selectedEmployee
                                                        .personalInformation
                                                        .civilStatus,
                                                ],
                                                [
                                                    'Team',
                                                    selectedEmployee.team ??
                                                        'Not assigned',
                                                ],
                                                [
                                                    'Schedule',
                                                    selectedEmployee.schedule ??
                                                        'Not assigned',
                                                ],
                                            ].map(([label, value]) => (
                                                <Box
                                                    key={label}
                                                    sx={{
                                                        p: 2,
                                                        border: '1px solid',
                                                        borderColor: 'divider',
                                                        borderRadius: 2,
                                                        bgcolor: '#fafafd',
                                                    }}
                                                >
                                                    <Typography
                                                        variant="caption"
                                                        color="text.secondary"
                                                    >
                                                        {label}
                                                    </Typography>
                                                    <Typography
                                                        variant="body2"
                                                        sx={{
                                                            fontWeight: 700,
                                                            mt: 0.5,
                                                            overflowWrap:
                                                                'anywhere',
                                                        }}
                                                    >
                                                        {value}
                                                    </Typography>
                                                </Box>
                                            ))}
                                        </Box>

                                        <Box>
                                            <Typography
                                                variant="caption"
                                                color="text.secondary"
                                            >
                                                Current address
                                            </Typography>
                                            <Typography
                                                variant="body2"
                                                sx={{
                                                    mt: 0.75,
                                                    fontWeight: 700,
                                                }}
                                            >
                                                {
                                                    selectedEmployee
                                                        .personalInformation
                                                        .address
                                                }
                                            </Typography>
                                        </Box>

                                        <Divider />

                                        <Box>
                                            <Typography
                                                variant="subtitle1"
                                                sx={{ fontWeight: 800 }}
                                            >
                                                Emergency Contact
                                            </Typography>
                                            <Box
                                                sx={{
                                                    display: 'grid',
                                                    gridTemplateColumns: {
                                                        xs: '1fr',
                                                        sm: 'repeat(2, 1fr)',
                                                    },
                                                    gap: 2,
                                                    mt: 2,
                                                }}
                                            >
                                                {[
                                                    [
                                                        'Contact name',
                                                        selectedEmployee
                                                            .personalInformation
                                                            .emergencyName,
                                                    ],
                                                    [
                                                        'Relationship',
                                                        selectedEmployee
                                                            .personalInformation
                                                            .emergencyRelationship,
                                                    ],
                                                    [
                                                        'Contact phone',
                                                        selectedEmployee
                                                            .personalInformation
                                                            .emergencyPhone,
                                                    ],
                                                    [
                                                        'Contact address',
                                                        selectedEmployee
                                                            .personalInformation
                                                            .emergencyAddress ||
                                                            'Not provided',
                                                    ],
                                                ].map(([label, value]) => (
                                                    <Box key={label}>
                                                        <Typography
                                                            variant="caption"
                                                            color="text.secondary"
                                                        >
                                                            {label}
                                                        </Typography>
                                                        <Typography
                                                            variant="body2"
                                                            sx={{
                                                                mt: 0.5,
                                                                fontWeight: 700,
                                                            }}
                                                        >
                                                            {value}
                                                        </Typography>
                                                    </Box>
                                                ))}
                                            </Box>
                                        </Box>
                                    </Stack>
                                ) : (
                                    <Alert severity="info">
                                        This account does not have a linked
                                        personal information record yet.
                                    </Alert>
                                )
                            ) : employeeEditForm && canManageEmployees ? (
                                <Stack spacing={3}>
                                    <Box>
                                        <Typography
                                            variant="subtitle1"
                                            sx={{ fontWeight: 800 }}
                                        >
                                            Account & Employee Editor
                                        </Typography>
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
                                            Changes are saved to the linked
                                            account and personal information.
                                        </Typography>
                                    </Box>

                                    <Box
                                        sx={{
                                            display: 'grid',
                                            gridTemplateColumns: {
                                                xs: '1fr',
                                                sm: 'repeat(2, 1fr)',
                                            },
                                            gap: 2,
                                        }}
                                    >
                                        <TextField
                                            label="Full Name"
                                            value={employeeEditForm.fullName}
                                            onChange={(event) =>
                                                updateEmployeeEditField(
                                                    'fullName',
                                                    event.target.value,
                                                )
                                            }
                                            error={Boolean(
                                                employeeEditErrors.full_name,
                                            )}
                                            helperText={
                                                employeeEditErrors.full_name
                                            }
                                        />
                                        <TextField
                                            select
                                            label="Position"
                                            value={employeeEditForm.position}
                                            onChange={(event) =>
                                                updateEmployeeEditField(
                                                    'position',
                                                    event.target.value,
                                                )
                                            }
                                            error={Boolean(
                                                employeeEditErrors.position,
                                            )}
                                        >
                                            {positions.map((option) => (
                                                <MenuItem
                                                    key={option}
                                                    value={option}
                                                >
                                                    {option}
                                                </MenuItem>
                                            ))}
                                        </TextField>
                                        <TextField
                                            label="Email Address"
                                            type="email"
                                            value={employeeEditForm.email}
                                            onChange={(event) =>
                                                updateEmployeeEditField(
                                                    'email',
                                                    event.target.value,
                                                )
                                            }
                                            error={Boolean(
                                                employeeEditErrors.email,
                                            )}
                                            helperText={
                                                employeeEditErrors.email
                                            }
                                        />
                                        <TextField
                                            select
                                            label="Employment Status"
                                            value={employeeEditForm.status}
                                            onChange={(event) =>
                                                updateEmployeeEditField(
                                                    'status',
                                                    event.target.value,
                                                )
                                            }
                                            error={Boolean(
                                                employeeEditErrors.status,
                                            )}
                                        >
                                            {[
                                                ['active', 'Active'],
                                                ['suspended', 'Suspended'],
                                                ['floating', 'Floating'],
                                                ['resigned', 'Resigned'],
                                                ['terminated', 'Terminated'],
                                            ].map(([value, label]) => (
                                                <MenuItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </MenuItem>
                                            ))}
                                        </TextField>
                                    </Box>

                                    {employeeEditForm.status !== 'active' && (
                                        <Alert severity="warning">
                                            Saving this status immediately
                                            disables this employee&apos;s login
                                            credentials.
                                        </Alert>
                                    )}

                                    <TextField
                                        label="Renew Password"
                                        type="password"
                                        value={employeeEditForm.newPassword}
                                        onChange={(event) =>
                                            updateEmployeeEditField(
                                                'newPassword',
                                                event.target.value,
                                            )
                                        }
                                        error={Boolean(
                                            employeeEditErrors.new_password,
                                        )}
                                        helperText={
                                            employeeEditErrors.new_password ??
                                            'Leave blank to keep the current password. Minimum 8 characters.'
                                        }
                                    />

                                    <Divider />
                                    <Typography
                                        variant="subtitle1"
                                        sx={{ fontWeight: 800 }}
                                    >
                                        Personal Information
                                    </Typography>
                                    <Box
                                        sx={{
                                            display: 'grid',
                                            gridTemplateColumns: {
                                                xs: '1fr',
                                                sm: 'repeat(2, 1fr)',
                                            },
                                            gap: 2,
                                        }}
                                    >
                                        {[
                                            [
                                                'birthDate',
                                                'Birth Date',
                                                'date',
                                                'birth_date',
                                            ],
                                            [
                                                'startDate',
                                                'Start Date',
                                                'date',
                                                'start_date',
                                            ],
                                            [
                                                'gender',
                                                'Gender',
                                                'text',
                                                'gender',
                                            ],
                                            [
                                                'civilStatus',
                                                'Civil Status',
                                                'text',
                                                'civil_status',
                                            ],
                                            [
                                                'phone',
                                                'Phone Number',
                                                'tel',
                                                'phone',
                                            ],
                                            [
                                                'address',
                                                'Current Address',
                                                'text',
                                                'address',
                                            ],
                                            [
                                                'emergencyName',
                                                'Emergency Contact Name',
                                                'text',
                                                'emergency_contact_name',
                                            ],
                                            [
                                                'emergencyRelationship',
                                                'Relationship',
                                                'text',
                                                'emergency_contact_relationship',
                                            ],
                                            [
                                                'emergencyPhone',
                                                'Emergency Contact Phone',
                                                'tel',
                                                'emergency_contact_phone',
                                            ],
                                            [
                                                'emergencyAddress',
                                                'Emergency Contact Address',
                                                'text',
                                                'emergency_contact_address',
                                            ],
                                        ].map(
                                            ([
                                                field,
                                                label,
                                                type,
                                                errorKey,
                                            ]) => (
                                                <TextField
                                                    key={field}
                                                    label={label}
                                                    type={type}
                                                    value={
                                                        employeeEditForm[
                                                            field as keyof EmployeeEditForm
                                                        ]
                                                    }
                                                    onChange={(event) =>
                                                        updateEmployeeEditField(
                                                            field as keyof EmployeeEditForm,
                                                            event.target.value,
                                                        )
                                                    }
                                                    error={Boolean(
                                                        employeeEditErrors[
                                                            errorKey
                                                        ],
                                                    )}
                                                    helperText={
                                                        employeeEditErrors[
                                                            errorKey
                                                        ]
                                                    }
                                                    slotProps={
                                                        type === 'date'
                                                            ? {
                                                                  inputLabel: {
                                                                      shrink: true,
                                                                  },
                                                              }
                                                            : undefined
                                                    }
                                                />
                                            ),
                                        )}
                                    </Box>
                                </Stack>
                            ) : null}
                        </DialogContent>
                        <DialogActions sx={{ px: 3.5, pb: 2.5 }}>
                            {['Agent', 'Team Leader'].includes(
                                selectedEmployee.position,
                            ) && (
                                <Button
                                    component="a"
                                    href={`/management/employees/${selectedEmployee.id}/training-profile`}
                                    variant="outlined"
                                >
                                    Training & Performance
                                </Button>
                            )}
                            {employeeModalTab === 1 && canManageEmployees && (
                                <Button
                                    variant="contained"
                                    onClick={saveEmployeeChanges}
                                    disabled={employeeEditProcessing}
                                >
                                    {employeeEditProcessing
                                        ? 'Saving...'
                                        : 'Save Changes'}
                                </Button>
                            )}
                            <Button
                                variant={
                                    employeeModalTab === 0
                                        ? 'contained'
                                        : 'outlined'
                                }
                                onClick={() => setSelectedEmployee(null)}
                            >
                                Close
                            </Button>
                        </DialogActions>
                    </>
                )}
            </Dialog>

            <Dialog
                open={createEmployeeOpen}
                onClose={() => setCreateEmployeeOpen(false)}
                fullWidth
                maxWidth="sm"
                slotProps={{
                    paper: {
                        sx: {
                            overflow: 'hidden',
                            maxWidth: 720,
                            borderRadius: 3,
                            m: { xs: 1.5, sm: 3 },
                        },
                    },
                }}
            >
                <Box
                    sx={{
                        position: 'relative',
                        overflow: 'hidden',
                        px: { xs: 2.5, sm: 3.5 },
                        py: { xs: 2.5, sm: 3 },
                        color: 'common.white',
                        background:
                            'linear-gradient(125deg, #251525 0%, #641d24 52%, #b12b23 100%)',
                    }}
                >
                    <Box
                        sx={{
                            position: 'absolute',
                            top: -100,
                            right: -60,
                            width: 220,
                            height: 220,
                            borderRadius: '50%',
                            background:
                                'radial-gradient(circle, rgba(255,190,111,.28), transparent 68%)',
                        }}
                    />
                    <Stack
                        direction="row"
                        spacing={1.5}
                        sx={{
                            position: 'relative',
                            alignItems: 'flex-start',
                            justifyContent: 'space-between',
                        }}
                    >
                        <Stack
                            direction="row"
                            spacing={1.75}
                            sx={{ alignItems: 'center' }}
                        >
                            <Box
                                sx={{
                                    display: 'grid',
                                    placeItems: 'center',
                                    width: 46,
                                    height: 46,
                                    borderRadius: 2.5,
                                    bgcolor: 'rgba(255,255,255,.13)',
                                    border: '1px solid rgba(255,255,255,.18)',
                                    backdropFilter: 'blur(10px)',
                                }}
                            >
                                <UserPlus size={22} />
                            </Box>
                            <Box>
                                <Typography
                                    variant="caption"
                                    sx={{
                                        color: 'rgba(255,255,255,.68)',
                                        letterSpacing: '.12em',
                                        fontWeight: 700,
                                        textTransform: 'uppercase',
                                    }}
                                >
                                    Employee onboarding
                                </Typography>
                                <Typography
                                    variant="h5"
                                    component="h2"
                                    sx={{
                                        mt: 0.25,
                                        color: 'common.white',
                                        fontWeight: 750,
                                    }}
                                >
                                    Add New Employee
                                </Typography>
                                <Typography
                                    variant="body2"
                                    sx={{
                                        mt: 0.4,
                                        color: 'rgba(255,255,255,.72)',
                                    }}
                                >
                                    Set up account details and access level.
                                </Typography>
                            </Box>
                        </Stack>
                        <IconButton
                            onClick={() => setCreateEmployeeOpen(false)}
                            aria-label="Close add employee dialog"
                            sx={{
                                position: 'absolute',
                                top: 0,
                                right: 0,
                                color: 'common.white',
                                bgcolor: 'rgba(255,255,255,.1)',
                                '&:hover': {
                                    bgcolor: 'rgba(255,255,255,.18)',
                                },
                            }}
                        >
                            <X size={20} />
                        </IconButton>
                    </Stack>
                </Box>

                <DialogTitle sx={{ px: { xs: 2.5, sm: 3.5 }, pt: 2.25, pb: 1 }}>
                    <Stepper
                        activeStep={employeeStep}
                        sx={{
                            '& .MuiStepLabel-label': { fontSize: 13 },
                            '& .MuiStepIcon-root': { fontSize: 23 },
                        }}
                    >
                        <Step>
                            <StepLabel>Employee details</StepLabel>
                        </Step>
                        <Step>
                            <StepLabel>Personal information</StepLabel>
                        </Step>
                        <Step>
                            <StepLabel>Face registration</StepLabel>
                        </Step>
                    </Stepper>
                </DialogTitle>

                <DialogContent sx={{ px: { xs: 2.5, sm: 3.5 }, pb: 1 }}>
                    <Divider sx={{ mb: 2 }} />

                    {employeeStep === 0 ? (
                        <Stack spacing={1.75}>
                            <Stack
                                direction="row"
                                spacing={1.25}
                                sx={{ alignItems: 'center' }}
                            >
                                <Box
                                    sx={{
                                        display: 'grid',
                                        placeItems: 'center',
                                        width: 32,
                                        height: 32,
                                        borderRadius: 2,
                                        color: 'primary.main',
                                        bgcolor: 'rgba(180,35,37,.09)',
                                    }}
                                >
                                    <ShieldCheck size={18} />
                                </Box>
                                <Box>
                                    <Typography
                                        variant="body1"
                                        sx={{
                                            fontWeight: 750,
                                            lineHeight: 1.3,
                                        }}
                                    >
                                        Account details
                                    </Typography>
                                    <Typography
                                        variant="caption"
                                        color="text.secondary"
                                    >
                                        Required information for system access
                                    </Typography>
                                </Box>
                            </Stack>

                            <Alert
                                severity="info"
                                icon={<Sparkles size={18} />}
                                sx={{
                                    borderRadius: 2,
                                    bgcolor: '#f5f7ff',
                                    color: '#414968',
                                    py: 0.25,
                                    fontSize: 13,
                                    '& .MuiAlert-icon': { color: '#6876c5' },
                                }}
                            >
                                The employee ID is generated from the next
                                available DVX account number.
                            </Alert>

                            <Box
                                sx={{
                                    display: 'grid',
                                    gridTemplateColumns: {
                                        xs: '1fr',
                                        sm: '1fr 1fr',
                                    },
                                    gap: 2,
                                }}
                            >
                                <TextField
                                    size="small"
                                    label="Employee ID"
                                    value={nextEmployeeId}
                                    fullWidth
                                    slotProps={{
                                        input: { readOnly: true },
                                    }}
                                    helperText="Automatically assigned"
                                />
                                <TextField
                                    size="small"
                                    label="Account Username"
                                    value={nextEmployeeId}
                                    fullWidth
                                    slotProps={{
                                        input: { readOnly: true },
                                    }}
                                    helperText="Employee login username"
                                />

                                <TextField
                                    size="small"
                                    label="Full Name"
                                    value={fullName}
                                    onChange={(event) =>
                                        setFullName(
                                            event.target.value.slice(0, 100),
                                        )
                                    }
                                    required
                                    fullWidth
                                    placeholder="Complete name"
                                    helperText={`${fullName.length}/100 characters`}
                                />

                                <TextField
                                    size="small"
                                    select
                                    label="Position"
                                    value={position}
                                    onChange={(event) =>
                                        setPosition(event.target.value)
                                    }
                                    required
                                    fullWidth
                                    helperText="Sets the initial access role"
                                >
                                    {positions.map((option) => (
                                        <MenuItem key={option} value={option}>
                                            {option}
                                        </MenuItem>
                                    ))}
                                </TextField>

                                <TextField
                                    size="small"
                                    label="Temporary Password"
                                    value={nextEmployeeId}
                                    fullWidth
                                    slotProps={{
                                        input: { readOnly: true },
                                    }}
                                    helperText="Must be changed after first sign-in"
                                    sx={{ gridColumn: { sm: '1 / -1' } }}
                                />
                            </Box>
                        </Stack>
                    ) : employeeStep === 1 ? (
                        <Stack spacing={2}>
                            <Stack
                                direction="row"
                                spacing={1.25}
                                sx={{ alignItems: 'center' }}
                            >
                                <Box
                                    sx={{
                                        display: 'grid',
                                        placeItems: 'center',
                                        width: 32,
                                        height: 32,
                                        borderRadius: 2,
                                        color: 'primary.main',
                                        bgcolor: 'rgba(180,35,37,.09)',
                                    }}
                                >
                                    <UserRound size={18} />
                                </Box>
                                <Box>
                                    <Typography
                                        variant="body1"
                                        sx={{
                                            fontWeight: 750,
                                            lineHeight: 1.3,
                                        }}
                                    >
                                        Personal information
                                    </Typography>
                                    <Typography
                                        variant="caption"
                                        color="text.secondary"
                                    >
                                        Employment and contact details
                                    </Typography>
                                </Box>
                            </Stack>

                            <Box
                                sx={{
                                    display: 'grid',
                                    gridTemplateColumns: {
                                        xs: '1fr',
                                        sm: '1fr 1fr',
                                    },
                                    gap: 2,
                                }}
                            >
                                <TextField
                                    size="small"
                                    type="date"
                                    label="Birth Date"
                                    value={personalInfo.birthDate}
                                    onChange={(event) =>
                                        updatePersonalInfo(
                                            'birthDate',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    fullWidth
                                    slotProps={{
                                        inputLabel: { shrink: true },
                                    }}
                                />
                                <TextField
                                    size="small"
                                    type="date"
                                    label="Employee Start Date"
                                    value={personalInfo.startDate}
                                    onChange={(event) =>
                                        updatePersonalInfo(
                                            'startDate',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    fullWidth
                                    slotProps={{
                                        inputLabel: { shrink: true },
                                    }}
                                />
                            </Box>

                            <Box
                                sx={{
                                    display: 'grid',
                                    gridTemplateColumns: {
                                        xs: '1fr',
                                        sm: '1fr 1fr',
                                    },
                                    gap: 2,
                                }}
                            >
                                <TextField
                                    size="small"
                                    select
                                    label="Gender"
                                    value={personalInfo.gender}
                                    onChange={(event) =>
                                        updatePersonalInfo(
                                            'gender',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    fullWidth
                                >
                                    {[
                                        'Female',
                                        'Male',
                                        'Non-binary',
                                        'Prefer not to say',
                                    ].map((option) => (
                                        <MenuItem key={option} value={option}>
                                            {option}
                                        </MenuItem>
                                    ))}
                                </TextField>
                                <TextField
                                    size="small"
                                    select
                                    label="Civil Status"
                                    value={personalInfo.civilStatus}
                                    onChange={(event) =>
                                        updatePersonalInfo(
                                            'civilStatus',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    fullWidth
                                >
                                    {[
                                        'Single',
                                        'Married',
                                        'Widowed',
                                        'Separated',
                                        'Divorced',
                                    ].map((option) => (
                                        <MenuItem key={option} value={option}>
                                            {option}
                                        </MenuItem>
                                    ))}
                                </TextField>
                                <TextField
                                    size="small"
                                    label="Phone Number"
                                    value={personalInfo.phone}
                                    onChange={(event) =>
                                        updatePersonalInfo(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    fullWidth
                                    placeholder="+63 900 000 0000"
                                    slotProps={{
                                        input: {
                                            startAdornment: (
                                                <Phone
                                                    size={16}
                                                    color="#9296a6"
                                                />
                                            ),
                                        },
                                    }}
                                />
                                <TextField
                                    size="small"
                                    type="email"
                                    label="Email Address"
                                    value={personalInfo.email}
                                    onChange={(event) =>
                                        updatePersonalInfo(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    fullWidth
                                    placeholder="employee@example.com"
                                />
                            </Box>

                            <TextField
                                size="small"
                                label="Current Address"
                                value={personalInfo.address}
                                onChange={(event) =>
                                    updatePersonalInfo(
                                        'address',
                                        event.target.value,
                                    )
                                }
                                required
                                fullWidth
                                multiline
                                minRows={2}
                                placeholder="Enter the employee's complete current address"
                            />

                            <Divider />

                            <Stack
                                direction="row"
                                spacing={1.25}
                                sx={{ alignItems: 'center' }}
                            >
                                <Box
                                    sx={{
                                        display: 'grid',
                                        placeItems: 'center',
                                        width: 32,
                                        height: 32,
                                        borderRadius: 2,
                                        color: 'secondary.dark',
                                        bgcolor: 'rgba(216,138,44,.11)',
                                    }}
                                >
                                    <HeartHandshake size={18} />
                                </Box>
                                <Box>
                                    <Typography
                                        variant="body1"
                                        sx={{
                                            fontWeight: 750,
                                            lineHeight: 1.3,
                                        }}
                                    >
                                        Emergency contact
                                    </Typography>
                                    <Typography
                                        variant="caption"
                                        color="text.secondary"
                                    >
                                        Person to contact in case of emergency
                                    </Typography>
                                </Box>
                            </Stack>

                            <Box
                                sx={{
                                    display: 'grid',
                                    gridTemplateColumns: {
                                        xs: '1fr',
                                        sm: '1fr 1fr',
                                    },
                                    gap: 2,
                                }}
                            >
                                <TextField
                                    size="small"
                                    label="Contact Name"
                                    value={personalInfo.emergencyName}
                                    onChange={(event) =>
                                        updatePersonalInfo(
                                            'emergencyName',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    fullWidth
                                    placeholder="Emergency contact name"
                                />
                                <TextField
                                    size="small"
                                    label="Relationship"
                                    value={personalInfo.emergencyRelationship}
                                    onChange={(event) =>
                                        updatePersonalInfo(
                                            'emergencyRelationship',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    fullWidth
                                    placeholder="Parent, spouse, sibling, etc."
                                />
                                <TextField
                                    size="small"
                                    label="Contact Phone"
                                    value={personalInfo.emergencyPhone}
                                    onChange={(event) =>
                                        updatePersonalInfo(
                                            'emergencyPhone',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    fullWidth
                                    placeholder="+63 900 000 0000"
                                />
                                <TextField
                                    size="small"
                                    label="Contact Address"
                                    value={personalInfo.emergencyAddress}
                                    onChange={(event) =>
                                        updatePersonalInfo(
                                            'emergencyAddress',
                                            event.target.value,
                                        )
                                    }
                                    fullWidth
                                    placeholder="Emergency contact address"
                                />
                            </Box>
                        </Stack>
                    ) : (
                        <Stack
                            spacing={2.25}
                            sx={{ alignItems: 'center', textAlign: 'center' }}
                        >
                            <Box>
                                <Typography
                                    variant="h6"
                                    sx={{ fontWeight: 750 }}
                                >
                                    DVX Face Enrollment
                                </Typography>
                                <Typography
                                    variant="body2"
                                    color="text.secondary"
                                    sx={{ mt: 0.5, maxWidth: 500 }}
                                >
                                    Allow camera access and position {fullName}
                                    inside the guide.
                                </Typography>
                            </Box>

                            <Box
                                sx={{
                                    position: 'relative',
                                    width: 320,
                                    maxWidth: '100%',
                                    aspectRatio: '4 / 3',
                                    overflow: 'hidden',
                                    borderRadius: 4,
                                    bgcolor: '#17131e',
                                    boxShadow: '0 18px 45px rgba(35,20,28,.18)',
                                }}
                            >
                                <Box
                                    component="video"
                                    ref={videoRef}
                                    muted
                                    playsInline
                                    sx={{
                                        display:
                                            cameraStatus === 'active'
                                                ? 'block'
                                                : 'none',
                                        width: '100%',
                                        height: '100%',
                                        objectFit: 'cover',
                                        transform: 'scaleX(-1)',
                                    }}
                                />
                                {cameraStatus !== 'active' && (
                                    <Box
                                        sx={{
                                            position: 'absolute',
                                            inset: 0,
                                            display: 'grid',
                                            placeItems: 'center',
                                            color: 'rgba(255,255,255,.72)',
                                        }}
                                    >
                                        {cameraStatus === 'denied' ? (
                                            <CameraOff size={54} />
                                        ) : (
                                            <Camera size={54} />
                                        )}
                                    </Box>
                                )}
                                <Box
                                    sx={{
                                        position: 'absolute',
                                        top: '50%',
                                        left: '50%',
                                        width: 142,
                                        height: 178,
                                        borderRadius: '46%',
                                        border: '3px solid rgba(255,255,255,.88)',
                                        boxShadow:
                                            '0 0 0 999px rgba(13,9,14,.28)',
                                        transform: 'translate(-50%, -50%)',
                                        animation:
                                            cameraStatus === 'requesting'
                                                ? 'dvx-face-pulse 1.4s ease-in-out infinite'
                                                : 'none',
                                        '@keyframes dvx-face-pulse': {
                                            '50%': { opacity: 0.4 },
                                        },
                                    }}
                                />
                                <Box
                                    sx={{
                                        position: 'absolute',
                                        right: 12,
                                        bottom: 10,
                                        borderRadius: 999,
                                        px: 1.2,
                                        py: 0.5,
                                        color: 'common.white',
                                        bgcolor:
                                            cameraStatus === 'active'
                                                ? 'rgba(16,145,96,.9)'
                                                : 'rgba(0,0,0,.55)',
                                        fontSize: 11,
                                        fontWeight: 700,
                                    }}
                                >
                                    {cameraStatus === 'active'
                                        ? faceDetectionStatus === 'submitting'
                                            ? 'Submitting'
                                            : faceDetectionStatus === 'scanning'
                                              ? 'Scanning'
                                              : 'Camera ready'
                                        : 'Camera off'}
                                </Box>
                            </Box>

                            {faceDetectionStatus !== 'idle' ? (
                                <Alert
                                    severity={
                                        faceDetectionStatus === 'error'
                                            ? 'warning'
                                            : faceDetectionStatus ===
                                                'submitting'
                                              ? 'success'
                                              : 'info'
                                    }
                                    icon={
                                        faceDetectionStatus === 'loading' ||
                                        faceDetectionStatus === 'scanning' ||
                                        faceDetectionStatus === 'submitting' ? (
                                            <CircularProgress
                                                color="inherit"
                                                size={20}
                                            />
                                        ) : undefined
                                    }
                                    sx={{
                                        width: '100%',
                                        borderRadius: 2,
                                        textAlign: 'left',
                                    }}
                                >
                                    {faceDetectionMessage}
                                </Alert>
                            ) : cameraStatus === 'active' ? (
                                <Alert
                                    severity="success"
                                    sx={{
                                        width: '100%',
                                        borderRadius: 2,
                                        textAlign: 'left',
                                    }}
                                >
                                    Camera connected. Keep the employee&apos;s
                                    face centered with even lighting and no
                                    other person in view.
                                </Alert>
                            ) : cameraStatus === 'denied' ? (
                                <Alert
                                    severity="warning"
                                    sx={{
                                        width: '100%',
                                        borderRadius: 2,
                                        textAlign: 'left',
                                    }}
                                >
                                    Camera access was blocked or no camera was
                                    found. Allow browser camera permission and
                                    try again.
                                </Alert>
                            ) : (
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                >
                                    Camera permission is required. Production
                                    access requires HTTPS.
                                </Typography>
                            )}

                            <FormControlLabel
                                control={
                                    <Checkbox
                                        checked={faceConsent}
                                        onChange={(event) =>
                                            setFaceConsent(event.target.checked)
                                        }
                                        disabled={
                                            faceDetectionStatus === 'loading' ||
                                            faceDetectionStatus ===
                                                'scanning' ||
                                            faceDetectionStatus === 'submitting'
                                        }
                                    />
                                }
                                label="The employee consents to encrypted face enrollment for account verification."
                                sx={{
                                    alignItems: 'flex-start',
                                    width: '100%',
                                    m: 0,
                                    '& .MuiFormControlLabel-label': {
                                        pt: 1,
                                        color: 'text.secondary',
                                        fontSize: 13,
                                    },
                                }}
                            />

                            <Button
                                variant="outlined"
                                onClick={startCamera}
                                disabled={
                                    !faceConsent ||
                                    cameraStatus === 'requesting' ||
                                    faceDetectionStatus === 'loading' ||
                                    faceDetectionStatus === 'scanning' ||
                                    faceDetectionStatus === 'submitting'
                                }
                                startIcon={<Camera size={18} />}
                            >
                                {cameraStatus === 'requesting'
                                    ? 'Requesting Camera…'
                                    : faceDetectionStatus === 'error'
                                      ? 'Try Face Enrollment Again'
                                      : 'Start Face Enrollment'}
                            </Button>
                        </Stack>
                    )}
                </DialogContent>

                <DialogActions
                    sx={{
                        px: { xs: 2.5, sm: 3.5 },
                        pt: 1.5,
                        pb: 2.5,
                        gap: 1,
                    }}
                >
                    {employeeStep === 0 ? (
                        <>
                            <Button
                                variant="outlined"
                                color="inherit"
                                onClick={() => setCreateEmployeeOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="contained"
                                disabled={!fullName.trim() || !position}
                                onClick={() => setEmployeeStep(1)}
                            >
                                Continue to Personal Information
                            </Button>
                        </>
                    ) : employeeStep === 1 ? (
                        <>
                            <Button
                                variant="outlined"
                                color="inherit"
                                onClick={() => setEmployeeStep(0)}
                            >
                                Back
                            </Button>
                            <Button
                                variant="contained"
                                startIcon={<ScanFace size={18} />}
                                disabled={!personalInfoComplete}
                                onClick={() => setEmployeeStep(2)}
                            >
                                Continue to Face Registration
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button
                                variant="outlined"
                                color="inherit"
                                onClick={() => setEmployeeStep(1)}
                                disabled={
                                    faceDetectionStatus === 'loading' ||
                                    faceDetectionStatus === 'scanning' ||
                                    faceDetectionStatus === 'submitting'
                                }
                            >
                                Back
                            </Button>
                            <Button
                                variant="contained"
                                startIcon={<ScanFace size={18} />}
                                disabled
                            >
                                {faceDetectionStatus === 'submitting'
                                    ? 'Creating Employee...'
                                    : 'Auto-submits after face verification'}
                            </Button>
                        </>
                    )}
                </DialogActions>
            </Dialog>
        </>
    );
}

Employees.layout = {
    breadcrumbs: [
        {
            title: 'Employees',
            href: '/employees',
        },
    ],
};
