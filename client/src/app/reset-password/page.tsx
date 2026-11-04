import { PasswordRecovery } from '@/components/auth/password-recovery';
export default async function ResetPasswordPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
    const params = await searchParams;
    const token = typeof params.token === 'string' ? params.token : undefined;
    const email = typeof params.email === 'string' ? params.email : undefined;
    return <PasswordRecovery token={token} email={email} />;
}
