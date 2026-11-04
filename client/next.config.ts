import type { NextConfig } from 'next';

const backend = (process.env.LERNUMI_API_URL ?? 'http://localhost:8000').replace(/\/$/, '');

const nextConfig: NextConfig = {
    experimental: {
        // Match PHP's multipart limit so document and tool uploads arrive intact.
        proxyClientMaxBodySize: '30mb',
    },
    async rewrites() {
        return [
            { source: '/api/:path*', destination: `${backend}/api/:path*` },
            { source: '/sanctum/:path*', destination: `${backend}/sanctum/:path*` },
        ];
    },
};

export default nextConfig;
