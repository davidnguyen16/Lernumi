'use client';

export function FormAlert({ message }: { message?: string }) {
    if (!message) return null;

    return (
        <div
            role="alert"
            className="border-destructive/30 bg-destructive/10 text-destructive rounded-lg border px-3 py-2 text-sm"
        >
            {message}
        </div>
    );
}
