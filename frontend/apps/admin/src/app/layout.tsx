import type { Metadata } from "next";
import type { ReactNode } from "react";
import "./globals.css";

export const metadata: Metadata = {
  title: "All Shop Admin — প্রস্তুতির পর্যায়",
  description: "All Shop administration workspace preview.",
};

export default function RootLayout({
  children,
}: Readonly<{ children: ReactNode }>) {
  return (
    <html lang="bn" className="h-full antialiased">
      <body className="min-h-full">{children}</body>
    </html>
  );
}
