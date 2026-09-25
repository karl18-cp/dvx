import { router, useForm } from '@inertiajs/react';
import { Camera, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useConfirmation } from '@/hooks/use-confirmation';
import type { User } from '@/types';

export default function ProfilePhoto({ user }: { user: User }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [removing, setRemoving] = useState(false);
    const photo = useForm<{ photo: File | null }>({ photo: null });
    const confirmAction = useConfirmation();
    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );
    const clear = () => {
        photo.reset();
        setPreview(null);

        if (fileInput.current) {
            fileInput.current.value = '';
        }
    };
    const remove = async () => {
        if (
            !(await confirmAction(
                'Remove your profile photo? Your account will use the default avatar.',
            ))
        ) {
            return;
        }

        router.delete('/settings/profile/photo', {
            preserveScroll: true,
            onStart: () => setRemoving(true),
            onFinish: () => setRemoving(false),
            onSuccess: clear,
            onError: (errors) =>
                photo.setError('photo', Object.values(errors).join(' ')),
        });
    };

    return (
        <section className="space-y-4 rounded-2xl border border-red-100 bg-[#fffafa] p-5">
            <div>
                <h2 className="text-lg font-bold">Profile picture</h2>
                <p className="mt-1 text-sm text-slate-500">
                    Add a photo for your account and sidebar.
                </p>
            </div>
            <div className="flex flex-wrap items-center gap-5">
                <Avatar className="size-24 border-4 border-white shadow-sm">
                    <AvatarImage
                        src={preview ?? user.avatar}
                        alt={preview ? 'New profile photo preview' : user.name}
                        className="object-cover"
                    />
                    <AvatarFallback className="bg-red-100 text-2xl font-bold text-red-800">
                        {user.name
                            .split(' ')
                            .filter(Boolean)
                            .slice(0, 2)
                            .map((part) => part[0])
                            .join('')}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 space-y-3">
                    <input
                        ref={fileInput}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        aria-label="Choose profile picture"
                        className="sr-only"
                        disabled={photo.processing || removing}
                        onChange={(event) => {
                            const file = event.target.files?.[0];

                            if (!file) {
                                return;
                            }

                            photo.clearErrors();

                            if (
                                ![
                                    'image/jpeg',
                                    'image/png',
                                    'image/webp',
                                ].includes(file.type) ||
                                file.size > 2 * 1024 * 1024
                            ) {
                                clear();
                                photo.setError(
                                    'photo',
                                    'Choose a JPG, PNG, or WebP image up to 2 MB.',
                                );

                                return;
                            }

                            photo.setData('photo', file);
                            setPreview(URL.createObjectURL(file));
                        }}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={photo.processing || removing}
                            onClick={() => fileInput.current?.click()}
                        >
                            <Camera className="size-4" />
                            Choose photo
                        </Button>
                        {user.avatar && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={photo.processing || removing}
                                onClick={remove}
                            >
                                <Trash2 className="size-4" />
                                Remove
                            </Button>
                        )}
                    </div>
                    <p className="text-xs text-slate-500">
                        JPG, PNG, or WebP · Up to 2 MB
                        <br />
                        64–4096 pixels per side. Square photos work best.
                    </p>
                </div>
            </div>
            <InputError message={photo.errors.photo} />
            {photo.data.photo && (
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        type="button"
                        disabled={photo.processing || removing}
                        onClick={() =>
                            photo.post('/settings/profile/photo', {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: clear,
                            })
                        }
                    >
                        {photo.processing
                            ? `Uploading${photo.progress ? ` ${photo.progress.percentage}%` : '…'}`
                            : 'Save photo'}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        disabled={photo.processing}
                        onClick={clear}
                    >
                        Cancel
                    </Button>
                    <span className="max-w-full truncate text-xs text-slate-500">
                        {photo.data.photo.name}
                    </span>
                </div>
            )}
        </section>
    );
}
