export default function Home() {
  return (
    <main className="grid min-h-screen place-items-center bg-[#f4f6f8] px-6 py-16 text-[#182331]">
      <section className="w-full max-w-2xl rounded-3xl border border-[#e1e7eb] bg-white p-8 shadow-sm sm:p-12">
        <div className="flex items-center gap-3">
          <span className="grid size-11 place-items-center rounded-2xl bg-[#183d58] text-sm font-black text-white">
            AS
          </span>
          <div>
            <p className="font-extrabold">All Shop</p>
            <p className="text-sm text-[#71808c]">Administration workspace</p>
          </div>
        </div>

        <p className="mt-12 text-sm font-bold uppercase tracking-[0.16em] text-[#3577a0]">
          M1 · Platform foundation
        </p>
        <h1 className="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
          Admin workspace প্রস্তুত হচ্ছে
        </h1>
        <p className="mt-5 leading-7 text-[#5f6e79]">
          Authentication, roles এবং permission checks এখনো যুক্ত হয়নি। এই
          preview-তে কোনো operational data নেই; identity ও access-control
          milestone সম্পন্ন না হওয়া পর্যন্ত এটি public deployment-এ ব্যবহার
          করবেন না।
        </p>
        <div className="mt-8 rounded-2xl bg-[#f0f5f8] p-5">
          <p className="font-bold">পরবর্তী ভিত্তি</p>
          <p className="mt-1 text-sm leading-6 text-[#5f6e79]">
            API contract, staff authentication, granular permissions এবং
            audit trail যোগ হওয়ার পর admin features শুরু হবে।
          </p>
        </div>
      </section>
    </main>
  );
}
