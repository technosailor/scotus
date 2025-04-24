import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  target: 'server',
  generateBuildId: () => null,
  port: process.env.PORT || 3000,
  disableServeStaticFilesDuringDevelopment: false,
};

export default nextConfig;
