import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

export default function EmployeeAvatar({
    name,
    avatar,
    className = 'size-10',
    fallbackClassName = 'bg-red-100 text-red-800',
}: {
    name: string;
    avatar?: string | null;
    className?: string;
    fallbackClassName?: string;
}) {
    const initials = name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
    return (
        <Avatar className={className}>
            <AvatarImage src={avatar || undefined} alt={name} />
            <AvatarFallback className={`font-bold ${fallbackClassName}`}>
                {initials}
            </AvatarFallback>
        </Avatar>
    );
}
