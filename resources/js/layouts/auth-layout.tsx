import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    immersive = false,
    children,
}: {
    title?: string;
    description?: string;
    immersive?: boolean;
    children: React.ReactNode;
}) {
    if (immersive) {
        return children;
    }

    return (
        <AuthLayoutTemplate title={title} description={description}>
            {children}
        </AuthLayoutTemplate>
    );
}
