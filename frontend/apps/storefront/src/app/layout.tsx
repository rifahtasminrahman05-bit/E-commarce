import type { Metadata } from "next";
import type { ReactNode } from "react";
import "./globals.css";

export const metadata: Metadata = {
  title: "All Shop — আপনার কেনাকাটার ঠিকানা",
  description: "All Shop-এর নতুন অনলাইন স্টোর তৈরি হচ্ছে।",
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
