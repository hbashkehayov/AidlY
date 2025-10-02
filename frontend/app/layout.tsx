import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import { Providers } from "@/components/providers";
import { Toaster } from "sonner";
import { FontSizeInitializer } from "@/components/font-size-initializer";

const inter = Inter({ subsets: ["latin"] });

export const metadata: Metadata = {
  title: "AidlY - Customer Support Platform",
  description: "Modern customer support platform built for teams",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body className={inter.className}>
        <script
          dangerouslySetInnerHTML={{
            __html: `
              (function() {
                try {
                  var fontSize = 'medium';
                  var user = null;

                  // Try to get user from localStorage
                  try {
                    var userStr = localStorage.getItem('user');
                    if (userStr) {
                      user = JSON.parse(userStr);
                    }
                  } catch (e) {
                    console.warn('Could not parse user data');
                  }

                  // Load user-specific font size if logged in
                  if (user && user.id) {
                    var savedSize = localStorage.getItem('fontSize_' + user.id);
                    if (savedSize) {
                      fontSize = savedSize;
                    }
                  }

                  document.documentElement.setAttribute('data-font-size', fontSize);
                } catch (e) {
                  console.error('Failed to load font size:', e);
                  document.documentElement.setAttribute('data-font-size', 'medium');
                }
              })();
            `,
          }}
        />
        <FontSizeInitializer />
        <Providers>
          {children}
          <Toaster richColors position="top-right" />
        </Providers>
      </body>
    </html>
  );
}
