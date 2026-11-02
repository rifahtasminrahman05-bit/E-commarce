import Link from "next/link";

export default function Home() {
  return (
    <main className="min-h-screen overflow-hidden bg-[#f7f8f4] text-[#18271f]">
      <header className="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-6 sm:px-10">
        <Link
          aria-label="All Shop home"
          className="flex items-center gap-3 text-xl font-extrabold tracking-tight"
          href="/"
        >
          <span className="grid size-10 place-items-center rounded-2xl bg-[#176b4d] text-sm text-white">
            AS
          </span>
          <span>All Shop</span>
        </Link>
        <span className="rounded-full border border-[#dce4dc] bg-white/80 px-4 py-2 text-sm font-semibold text-[#42604e]">
          স্টোর প্রস্তুত হচ্ছে
        </span>
      </header>

      <section className="mx-auto grid min-h-[76vh] w-full max-w-7xl items-center gap-12 px-6 pb-20 pt-10 sm:px-10 lg:grid-cols-[1.1fr_0.9fr]">
        <div className="relative z-10 max-w-2xl">
          <p className="mb-6 inline-flex items-center gap-2 rounded-full bg-[#e5f1e9] px-4 py-2 text-sm font-bold text-[#176b4d]">
            <span className="size-2 rounded-full bg-[#27a36b]" />
            বাংলাদেশের জন্য নতুন অনলাইন শপ
          </p>
          <h1 className="text-5xl font-black leading-[1.12] tracking-tight sm:text-6xl lg:text-7xl">
            আপনার পছন্দের
            <br />
            <span className="text-[#20805b]">সবকিছু এক</span>
            <br />
            জায়গায়।
          </h1>
          <p className="mt-7 max-w-xl text-lg leading-8 text-[#607166]">
            All Shop-এর নতুন অভিজ্ঞতা তৈরি হচ্ছে। পণ্যের ক্যাটালগ, নিরাপদ
            checkout এবং অর্ডার ব্যবস্থাপনা ধাপে ধাপে যুক্ত হবে।
          </p>
          <div className="mt-9 flex flex-wrap items-center gap-3">
            <a
              className="rounded-xl bg-[#176b4d] px-6 py-3.5 font-bold text-white transition-colors hover:bg-[#10583e]"
              href="#progress"
            >
              অগ্রগতি দেখুন
            </a>
            <span className="text-sm font-medium text-[#718078]">
              বাংলা · English · BDT
            </span>
          </div>
        </div>

        <div className="relative mx-auto flex aspect-square w-full max-w-[500px] items-center justify-center">
          <div className="absolute inset-8 rounded-[3rem] bg-[#dcebdd] rotate-6" />
          <div className="absolute inset-8 rounded-[3rem] bg-[#eaf0e8] -rotate-6" />
          <div className="relative grid size-[78%] place-items-center rounded-[2.5rem] bg-[#176b4d] p-8 text-center text-white shadow-2xl shadow-[#176b4d]/20">
            <div>
              <span aria-hidden="true" className="text-7xl">✳</span>
              <p className="mt-5 text-3xl font-black">All Shop</p>
              <p className="mt-2 text-sm text-white/75">শিগগিরই আসছে</p>
            </div>
          </div>
          <span className="absolute right-0 top-[18%] rounded-2xl bg-white px-4 py-3 text-sm font-bold text-[#365442] shadow-lg">
            আপনার কেনাকাটা,
            <br />
            আরও সহজ
          </span>
          <span className="absolute bottom-[13%] left-0 rounded-2xl bg-[#f0c97a] px-4 py-3 text-sm font-extrabold text-[#443716] shadow-lg">
            বাংলাদেশজুড়ে
          </span>
        </div>
      </section>

      <section
        className="border-t border-[#e4e9e1] bg-white"
        id="progress"
      >
        <div className="mx-auto grid w-full max-w-7xl gap-6 px-6 py-10 sm:px-10 md:grid-cols-3">
          <div>
            <p className="text-sm font-bold uppercase tracking-[0.16em] text-[#20805b]">
              এখনকার ধাপ
            </p>
            <h2 className="mt-2 text-xl font-extrabold">প্ল্যাটফর্মের ভিত্তি</h2>
          </div>
          <p className="leading-7 text-[#607166]">
            এই preview-তে এখনো পণ্য, দাম বা অর্ডারের live তথ্য নেই। সেগুলো
            backend API তৈরি ও যাচাই হওয়ার পর দেখানো হবে।
          </p>
          <p className="leading-7 text-[#607166]">
            কেনাকাটা এখনো চালু হয়নি। বর্তমান PHP storefront আলাদা এবং
            অনুমোদিত migration না হওয়া পর্যন্ত অপরিবর্তিত থাকবে।
          </p>
        </div>
      </section>

      <footer className="mx-auto flex w-full max-w-7xl flex-col gap-2 px-6 py-7 text-sm text-[#718078] sm:flex-row sm:items-center sm:justify-between sm:px-10">
        <span>© All Shop</span>
        <span>একটি নতুন অভিজ্ঞতা তৈরি হচ্ছে</span>
      </footer>
    </main>
  );
}
