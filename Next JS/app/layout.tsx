import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "KPN Validation Test - Next.js",
  description: "KPN Validation Test API built with Next.js",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
