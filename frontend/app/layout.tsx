import type { Metadata } from 'next';
import './globals.css';
export const metadata: Metadata = { title: 'ParkFlow — Smart Parking', description: 'Sistem operasional parkir, tiket digital, dan pembayaran.' };
export default function RootLayout({ children }: { children: React.ReactNode }) { return <html lang="id"><body>{children}</body></html>; }
